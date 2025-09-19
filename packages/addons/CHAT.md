
# Chat Component

Chat orchestrates multi‑turn, multi‑participant conversations with modular step processing and pluggable continuation criteria. It provides a clean, event-driven architecture for building complex conversational systems with LLMs, tools, and external participants.

This document explains how to use Chat, customize its behavior, and integrate it with your applications.

## Overview

- **Orchestrator**: `Cognesy\Addons\Chat\Chat` — stateless service that processes one turn at a time
- **Configuration**: `Data\ChatConfig` — immutable configuration object containing participants, selectors, and processors
- **State**: `Data\ChatState` — immutable state object containing messages, variables, steps, and usage
- **Step**: `Data\ChatStep` — represents one participant's action with input/output messages and metadata
- **Participants** (`Contracts\CanParticipateInChat`):
  - `Participants\LLMParticipant` — assistant using Polyglot Inference with optional system prompts
  - `Participants\LLMWithToolsParticipant` — assistant with tool-calling capabilities via ToolUse
  - `Participants\ExternalParticipant` — external input (human, API, etc.) via callbacks or providers
  - `Participants\ScriptedParticipant` — pre-scripted responses for testing and demos
- **Participant selection** (`Contracts\CanChooseNextParticipant`):
  - `Selectors\RoundRobinSelector` — cycles through participants in order
  - `Selectors\LLMBasedCoordinator` — AI-powered participant selection using structured output
- **Step processors** (`Contracts\CanProcessChatStep`): process steps after participant actions
- **Continuation criteria** ( helpers): determine when chat should continue
- **Collections**: Type-safe collections for participants, steps, processors, and criteria
- **Observability**: Comprehensive event system for monitoring chat lifecycle

## Quick Start (Human → Assistant)

```php
use Cognesy\Addons\Chat\Chat;use Cognesy\Addons\Chat\Data\ChatConfig;use Cognesy\Addons\Chat\Data\ChatState;use Cognesy\Addons\Chat\Data\Collections\Participants;use Cognesy\Addons\Chat\Participants\LLMParticipant;use Cognesy\Messages\Messages;

// Create participants
$participants = new Participants(
    new LLMParticipant(name: 'assistant')
);

// Create configuration with default settings
$config = ChatConfig::default($participants);

// Create initial state with user message
$initialMessages = Messages::fromArray([
    ['role' => 'user', 'content' => 'Hello, how are you?']
]);
$state = new ChatState(messages: $initialMessages);

// Execute one turn
$chat = new Chat($config);
$newState = $chat->nextStep($state);

// Get the assistant's response
$assistantMessage = $newState->steps()->last()->outputMessage();
echo $assistantMessage->content();
```

**Key concepts:**
- `Chat` requires a `ChatConfig` with participants and configuration
- `ChatState` holds the conversation state (messages, variables, steps, usage)
- `nextTurn()` takes a state and returns a new updated state
- Default configuration includes step processors and continuation criteria

## Multi-Participant Chat Example

```php
use Cognesy\Addons\Chat\Chat;
use Cognesy\Addons\Chat\Continuation\Criteria as ChatCriteria;
use Cognesy\Addons\Chat\Data\ChatConfig;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Core\Continuation\ContinuationCriteria;
use Cognesy\Addons\Chat\Data\Collections\Participants;
use Cognesy\Addons\Chat\Participants\ExternalParticipant;
use Cognesy\Addons\Chat\Participants\LLMParticipant;
use Cognesy\Addons\Chat\Selectors\RoundRobinSelector;

// Create participants with different roles
$human = new ExternalParticipant(
    name: 'user',
    provider: function() {
        static $questions = [
            'What are the key challenges in AI development?',
            'How do you see the future of AI?',
            'Thanks for the insights!'
        ];
        static $index = 0;
        $question = $questions[$index++] ?? 'No more questions';
        return new \Cognesy\Messages\Message(role: 'user', content: $question);
    }
);

$researcher = new LLMParticipant(
    name: 'researcher',
    systemPrompt: 'You are Dr. Sarah Chen, an AI researcher. Focus on academic perspectives. Be concise.'
);

$practitioner = new LLMParticipant(
    name: 'practitioner',
    systemPrompt: 'You are Marcus, an AI engineer. Focus on practical implementation. Be concise.'
);

// Create configuration
$participants = new Participants($human, $researcher, $practitioner);
$config = ChatConfig::default($participants)
    ->withNextParticipantSelector(new RoundRobinSelector())
    ->withContinuationCriteria(new ContinuationCriteria(ChatCriteria::stepsLimit(9)));

// Create initial state
$state = new ChatState();

// Run conversation
$chat = new Chat($config);
while ($chat->hasNextStep($state)) {
    $state = $chat->nextStep($state);
    $step = $state->currentStep();
    if ($step) {
        echo "[{$step->participantName()}]: {$step->outputMessage()->content()}\n\n";
    }
}
```


## Participants

### `LLMParticipant`
Basic LLM participant using Polyglot Inference for generating responses.

Parameters:
- `name: string` - Participant identifier (default: 'assistant')
- `inference?: Inference` - Custom Inference instance (useful for testing with mocks)
- `llmProvider?: LLMProvider` - Custom LLM provider
- `systemPrompt?: string` - System prompt specific to this participant

```php
$assistant = new LLMParticipant(
    name: 'assistant',
    systemPrompt: 'You are a helpful assistant.'
);
```

### `LLMWithToolsParticipant`
LLM participant with tool-calling capabilities, integrating with the ToolUse system.

Parameters:
- `name: string` - Participant identifier (default: 'assistant-with-tools')
- `toolUse: ToolUse` - ToolUse instance with configured tools
- `systemPrompt?: string` - System prompt specific to this participant

```php
$toolsAssistant = new LLMWithToolsParticipant(
    name: 'assistant-with-tools',
    toolUse: $toolUse,
    systemPrompt: 'You are an assistant with access to tools.'
);
```

### `ExternalParticipant`
For external input sources (human, API, other systems).

Parameters:
- `name: string` - Participant identifier (default: 'external')
- `provider: callable|CanProvideMessage` - Message provider function or object

```php
// With callable provider
$human = new ExternalParticipant(
    name: 'user',
    provider: fn() => new Message(role: 'user', content: 'Hello!')
);

// With CanProvideMessage implementation
$human = new ExternalParticipant(
    name: 'user',
    provider: new MyMessageProvider()
);
```

### `ScriptedParticipant`
Pre-scripted responses, useful for testing and demonstrations.

Parameters:
- `name: string` - Participant identifier
- `messages: array` - Array of message strings to cycle through

```php
$storeed = new ScriptedParticipant(
    name: 'demo',
    messages: ['Hello!', 'How are you?', 'Goodbye!']
);
```

## Participant Selection

Available selectors:

### `RoundRobinSelector`
Cycles through participants in sequential order.

```php
$selector = new RoundRobinSelector();
```

### `LLMBasedCoordinator`
AI-powered participant selection using structured output to choose the most appropriate participant based on conversation context.

Parameters:
- `structuredOutput?: StructuredOutput` - Custom StructuredOutput instance
- `instruction?: string` - Custom instruction for participant selection

```php
$llmSelector = new LLMBasedCoordinator(
    instruction: 'Choose the next participant who should respond based on the conversation context.'
);
```

**LLMBasedCoordinator features**: 
- Returns structured `ParticipantChoice` with participant name and reasoning
- Type-safe selection with validation and retry logic
- Uses full conversation context to make informed decisions
- Falls back to first participant if selection fails

Configure selector in ChatConfig:

```php
$config = ChatConfig::default($participants)
    ->withNextParticipantSelector(new RoundRobinSelector());

// Or with AI-powered selection
$config = ChatConfig::default($participants)
    ->withNextParticipantSelector(new LLMBasedCoordinator());
```

## Continuation Criteria

Available criteria determine when the chat should stop:

### `StepsLimit`
Stop after a maximum number of turns.
```php
$stepsLimit = new StepsLimit(maxSteps: 10);
```

### `TokenUsageLimit`
Stop when total token usage exceeds a limit.
```php
$tokenLimit = ChatCriteria::tokenUsageLimit(maxTokens: 4000);
```

### `ExecutionTimeLimit`
Stop after a time limit (in seconds).
```php
$timeLimit = ChatCriteria::executionTimeLimit(seconds: 300); // 5 minutes
```

### `FinishReasonCheck`
Stop when specific finish reasons are encountered.
```php
$finishCheck = ChatCriteria::finishReasonCheck(['stop', 'length']);
```

Configure in ChatConfig:

```php
use Cognesy\Addons\Core\Continuation\ContinuationCriteria;
use Cognesy\Addons\Chat\Continuation\Criteria as ChatCriteria;

$criteria = new ContinuationCriteria(
    ChatCriteria::stepsLimit(10),
    ChatCriteria::tokenUsageLimit(4000),
);

$config = ChatConfig::default($participants)
    ->withContinuationCriteria($criteria);
```

**Default behavior**: The default configuration includes basic continuation criteria (FinishReasonCheck, StepsLimit of 16, TokenUsageLimit of 4096). All criteria must return `true` for the chat to continue.

## Step Processors

Step processors (`CanProcessChatStep`) handle chat steps after participant actions. They implement the `process(ChatStep $step, ChatState $state): ChatState` method.

### Built-in Step Processors

#### `AccumulateTokenUsage`
Accumulates token usage from each step into the chat state.
```php
$processor = new AccumulateTokenUsage();
```

#### `AddCurrentStep`
Adds the current step to the chat state's step history.
```php
$processor = new AddCurrentStep();
```

#### `AppendStepMessages`
Appends step messages to the chat state's message history.
```php
$processor = new AppendStepMessages();
```

#### `MoveMessagesToBuffer`
Moves messages to a buffer when context window limits are approached.
```php
$processor = new MoveMessagesToBuffer();
```

#### `SummarizeBuffer`
Summarizes buffered messages to preserve context while reducing token usage.
```php
$processor = new SummarizeBuffer();
```

### Custom Step Processors
Create custom processors by implementing `CanProcessChatStep`:

```php
class MyCustomProcessor implements CanProcessChatStep
{
    public function process(ChatStep $step, ChatState $state): ChatState
    {
        // Your custom processing logic
        return $state->withVariable('processed', true);
    }
}
```

### Configuration
Configure step processors in ChatConfig:

```php
use Cognesy\Addons\Chat\Data\Collections\ChatStateProcessors;

$processors = new ChatStateProcessors(
    new AccumulateTokenUsage(),
    new AddCurrentStep(),
    new AppendStepMessages(),
    new MyCustomProcessor()
);

$config = ChatConfig::default($participants)
    ->withStepProcessors($processors);
```

**Default processors**: The default configuration includes `AppendStepMessages`, `AddCurrentStep`, and `AccumulateTokenUsage`.

## Observability (Events)

The Chat system provides comprehensive event monitoring throughout the conversation lifecycle. All events carry detailed data for logging and monitoring.

### Available Events

- **`ChatStarted`** - Fired when a new chat begins
- **`ChatTurnStarting`** - Fired at the beginning of each turn with turn number and state
- **`ChatParticipantSelected`** - Fired when a participant is chosen with participant details
- **`ChatBeforeSend`** - Fired before sending messages to a participant
- **`ChatInferenceRequested`** - Fired when inference is requested from LLM participants
- **`ChatInferenceResponseReceived`** - Fired when inference response is received
- **`ChatToolUseStarted`** - Fired when tool use begins (for LLMWithToolsParticipant)
- **`ChatToolUseCompleted`** - Fired when tool use completes
- **`ChatTurnCompleted`** - Fired when a turn is completed with step details
- **`ChatStateUpdated`** - Fired when chat state changes
- **`ChatCompleted`** - Fired when the chat ends with completion reason

### Event Data Examples
```php
// ChatTurnStarting
['turn' => 1, 'state' => $state->toArray()]

// ChatParticipantSelected
['participantName' => 'assistant', 'participantClass' => 'LLMParticipant', 'participant' => $participant]

// ChatCompleted
['state' => $state->toArray(), 'reason' => 'has no next turn']
```

### Event Handling
Configure event handlers when creating the Chat instance:
```php
$eventBus = new MyEventBus();
$chat = new Chat($config, $eventBus);
```

## Testing

### Deterministic Testing (No Real LLM Calls)

Use mocked inference for predictable responses:

```php
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Addons\Chat\Participants\LLMParticipant;

// Create mock inference that returns predictable responses
$mockInference = $this->createMock(Inference::class);
$mockInference->method('response')->willReturn($mockResponse);

$assistant = new LLMParticipant(
    name: 'assistant',
    inference: $mockInference
);
```

### Using ScriptedParticipant for Testing

For deterministic testing, use `ScriptedParticipant`:

```php
use Cognesy\Addons\Chat\Participants\ScriptedParticipant;

$storeed = new ScriptedParticipant(
    name: 'assistant',
    messages: ['Hello!', 'How can I help?', 'Goodbye!']
);
```

### Feature Test Example

```php
use Cognesy\Addons\Chat\Chat;
use Cognesy\Addons\Chat\Continuation\Criteria as ChatCriteria;
use Cognesy\Addons\Chat\Data\ChatConfig;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\Collections\Participants;
use Cognesy\Messages\Messages;

// Create test configuration
$participants = new Participants($storeedAssistant);
$config = ChatConfig::default($participants)
    ->withContinuationCriteria(new ContinuationCriteria(ChatCriteria::stepsLimit(2)));

// Create initial state with user message
$initialMessages = Messages::fromArray([
    ['role' => 'user', 'content' => 'Hello']
]);
$state = new ChatState(messages: $initialMessages);

// Execute chat turn
$chat = new Chat($config);
$newState = $chat->nextStep($state);

// Verify results
expect($newState->steps()->count())->toBe(1);
expect($newState->messages()->count())->toBe(2); // user + assistant
```

### Key Testing Patterns

1. **Use deterministic participants**: ScriptedParticipant or mocked LLMParticipant
2. **Set continuation criteria**: Limit turns to prevent infinite loops
3. **Verify state changes**: Check step count, message count, and content
4. **Test event emissions**: Verify events are fired correctly
5. **Mock external dependencies**: Use test doubles for inference and tools

## Architecture & Design

### Chat Class
- **Stateless** - processes one turn at a time
- Configured via immutable `ChatConfig`
- Safe to reuse across multiple conversations
- Thread-safe operation

### ChatConfig
- **Immutable** configuration object
- Contains participants, selectors, processors, and criteria
- Can be reused across multiple chat instances
- Provides sensible defaults via `ChatConfig::default()`

### ChatState
- **Immutable** - every operation returns a new state instance
- Contains complete conversation state: messages, variables, steps, usage
- Can be serialized for conversation persistence
- Thread-safe - safe to pass between processes

### ChatStep
- **Immutable** record of a single participant action
- Contains input messages, output message, usage, and metadata
- Includes participant name and timing information
- Can be serialized for audit trails

### Collections
All collections are **immutable** and **type-safe**:
- `Participants` - Collection of chat participants
- `ChatSteps` - Collection of conversation steps
- `StepProcessors` - Collection of step processors
- `ContinuationCriteria` - Collection of continuation criteria

### Best Practices
- Reuse `ChatConfig` across conversations
- Create new `ChatState` for each conversation
- Store/restore `ChatState` for conversation persistence
- Pass updated state between `nextTurn()` calls
- Use collections for type safety and immutability

## Design Principles

- **Immutability**: All core objects (`ChatState`, `ChatStep`, `ChatConfig`, collections) are immutable
- **Separation of concerns**: 
  - Participants generate content
  - Chat orchestrates conversation flow
  - Step processors handle post-action logic
  - Selectors coordinate participant turns
  - Continuation criteria determine when to stop
- **Event-driven**: Comprehensive event system for observability and extensibility
- **Type safety**: Strong typing with dedicated collection classes
- **Extensibility**: Plugin architecture for participants, processors, and criteria
- **Testability**: Built-in support for deterministic testing

## Troubleshooting

### Chat doesn't progress
**Problem**: `nextTurn()` returns immediately without any participant action  
**Solution**: Ensure your configuration has participants and valid continuation criteria

### No participants available
**Problem**: "No participants available to select from" error  
**Solution**: Add participants to your configuration:
```php
$participants = new Participants(new LLMParticipant(name: 'assistant'));
$config = ChatConfig::default($participants);
```

### Chat stops unexpectedly
**Problem**: Conversation ends after one turn  
**Solutions**:
- Check continuation criteria - they might be too restrictive
- Verify token limits aren't exceeded
- Check for finish reasons that stop the conversation

### Role mapping issues in multi-participant chats
**Problem**: LLM participants get confused about message roles  
**Solution**: LLMParticipant handles role mapping automatically in `prepareMessages()` method

### Context window issues
**Problem**: Conversation becomes too long, hitting token limits  
**Solution**: Use context management processors:
```php
$processors = new StepProcessors(
    new MoveMessagesToBuffer(),
    new SummarizeBuffer(),
    new AccumulateTokenUsage(),
    new AddCurrentStep()
);
```

### Tests are non-deterministic
**Problem**: Tests fail randomly due to real LLM calls  
**Solution**: Use `ScriptedParticipant` or mocked `LLMParticipant` for predictable responses
