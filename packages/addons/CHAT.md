
# Chat Component

Chat orchestrates multi‑turn, multi‑participant conversations with modular step processing and pluggable continuation criteria. It provides a clean, event-driven architecture for building complex conversational systems with LLMs, tools, and external participants.

This document explains how to use Chat, customize its behavior, and integrate it with your applications.

## Overview

The Chat component extends the StepByStep framework to orchestrate multi-participant conversations:

- **Factory**: `Cognesy\Addons\Chat\ChatFactory` — convenient factory for creating Chat instances with sensible defaults
- **Orchestrator**: `Cognesy\Addons\Chat\Chat` — extends StepByStep to process conversation turns iteratively
- **State**: `Data\ChatState` — immutable state object containing messages, variables, steps, and usage
- **Step**: `Data\ChatStep` — represents one participant's action with input/output messages and metadata
- **Participants** (`Contracts\CanParticipateInChat`):
  - `Participants\LLMParticipant` — assistant using Polyglot Inference with optional system prompts
  - `Participants\LLMParticipantWithTools` — assistant with tool-calling capabilities via ToolUse
  - `Participants\ExternalParticipant` — external input (human, API, etc.) via callbacks or providers
  - `Participants\ScriptedParticipant` — pre-scripted responses for testing and demos
- **Participant selection** (`Contracts\CanChooseNextParticipant`):
  - `Selectors\RoundRobinSelector` — cycles through participants in order
  - `Selectors\LLMBasedCoordinator` — AI-powered participant selection using structured output
- **State processors**: Process and transform state after each step using the StepByStep framework
- **Continuation criteria**: Determine when chat should continue using the StepByStep framework
- **Collections**: Type-safe collections for participants, steps, processors, and criteria
- **Observability**: Comprehensive event system for monitoring chat lifecycle

## Quick Start (Human → Assistant)

```php
use Cognesy\Addons\Chat\ChatFactory;use Cognesy\Addons\Chat\Collections\Participants;use Cognesy\Addons\Chat\Data\ChatState;use Cognesy\Addons\Chat\Participants\LLMParticipant;use Cognesy\Messages\Messages;

// Create participants
$participants = new Participants(
    new LLMParticipant(name: 'assistant')
);

// Create chat using factory with default settings
$chat = ChatFactory::default($participants);

// Create initial state with user message
$initialMessages = Messages::fromArray([
    ['role' => 'user', 'content' => 'Hello, how are you?']
]);
$state = (new ChatState())->withMessages($initialMessages);

// Execute one turn
$newState = $chat->nextStep($state);

// Get the assistant's response
echo $newState->messages()->toString();
```

**Key concepts:**
- `ChatFactory::default()` provides sensible defaults with participants, processors, and continuation criteria
- `ChatState` holds the conversation state (messages, metadata, steps, usage)
- `nextStep()` takes a state and returns a new updated state (from StepByStep pattern)
- Default configuration includes `AppendStepMessages` and `AccumulateTokenUsage` processors
- Chat extends StepByStep providing `nextStep()`, `hasNextStep()`, `finalStep()`, and `iterator()` methods

## Multi-Participant Chat Example

```php
use Cognesy\Addons\Chat\ChatFactory;use Cognesy\Addons\Chat\Collections\Participants;use Cognesy\Addons\Chat\Data\ChatState;use Cognesy\Addons\Chat\Participants\LLMParticipant;use Cognesy\Addons\Chat\Participants\ScriptedParticipant;use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;use Cognesy\Addons\StepByStep\Continuation\Criteria\ResponseContentCheck;use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;use Cognesy\Messages\Messages;

// Create participants with different roles
$moderator = new ScriptedParticipant(
    name: 'moderator',
    messages: [
        'Welcome! What are the key challenges in AI development?',
        'How do you see the future of AI?',
        'Thanks for the insights!',
        '', // Empty message signals end
    ]
);

$researcher = new LLMParticipant(
    name: 'researcher',
    systemPrompt: 'You are Dr. Sarah Chen, an AI researcher. Focus on academic perspectives. Be concise.'
);

$practitioner = new LLMParticipant(
    name: 'practitioner',
    systemPrompt: 'You are Marcus, an AI engineer. Focus on practical implementation. Be concise.'
);

// Create chat with custom continuation criteria
$participants = new Participants($moderator, $researcher, $practitioner);
$chat = ChatFactory::default(
    participants: $participants,
    continuationCriteria: new ContinuationCriteria(
        new StepsLimit(10, fn($state) => $state->stepCount()),
        new ResponseContentCheck(
            fn($state) => $state->currentStep()?->outputMessages(),
            static fn(Messages $response) => $response->last()->content()->toString() !== ''
        ),
    )
);

// Create initial state
$state = new ChatState();

// Run conversation
while ($chat->hasNextStep($state)) {
    $state = $chat->nextStep($state);
    $step = $state->currentStep();
    if ($step) {
        echo "[{$step->participantName()}]: {$step->outputMessages()->last()->content()}\n\n";
    }
}
```


## Participants

### `LLMParticipant`
Basic LLM participant using Polyglot Inference for generating responses.

Parameters:
- `name: string` - Participant identifier (default: 'assistant')
- `systemPrompt?: string` - System prompt specific to this participant
- `inference?: Inference` - Custom Inference instance (useful for testing with mocks)
- `llmProvider?: LLMProvider` - Custom LLM provider
- `compiler?: CanCompileMessages` - Message compiler (default: AllSections)
- `events?: CanHandleEvents` - Event handler for monitoring

```php
$assistant = new LLMParticipant(
    name: 'assistant',
    systemPrompt: 'You are a helpful assistant.'
);
```

### `LLMParticipantWithTools`
LLM participant with tool-calling capabilities, integrating with the ToolUse system.

Parameters:
- `name: string` - Participant identifier (default: 'assistant-with-tools')
- `systemPrompt?: string` - System prompt specific to this participant
- `toolUse?: ToolUse` - ToolUse instance with configured tools (uses default if not provided)
- `events?: CanHandleEvents` - Event handler for monitoring

```php
$toolsAssistant = new LLMParticipantWithTools(
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

Continuation criteria determine when the chat should stop. All criteria are part of the StepByStep framework:

### `StepsLimit`
Stop after a maximum number of turns.
```php
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\StepByStep\State\Contracts\HasSteps;

$stepsLimit = new StepsLimit(10, fn(HasSteps $state) => $state->stepCount());
```

### `TokenUsageLimit`
Stop when total token usage exceeds a limit.
```php
use Cognesy\Addons\StepByStep\Continuation\Criteria\TokenUsageLimit;
use Cognesy\Addons\StepByStep\State\Contracts\HasUsage;

$tokenLimit = new TokenUsageLimit(4000, fn(HasUsage $state) => $state->usage()->total());
```

### `ExecutionTimeLimit`
Stop after a time limit (in seconds).
```php
use Cognesy\Addons\StepByStep\Continuation\Criteria\ExecutionTimeLimit;
use Cognesy\Addons\StepByStep\State\Contracts\HasStateInfo;

$timeLimit = new ExecutionTimeLimit(300, fn(HasStateInfo $state) => $state->startedAt()); // 5 minutes
```

### `FinishReasonCheck`
Stop when specific finish reasons are encountered.
```php
use Cognesy\Addons\StepByStep\Continuation\Criteria\FinishReasonCheck;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;

$finishCheck = new FinishReasonCheck([
    InferenceFinishReason::Stop,
    InferenceFinishReason::Length,
], fn(ChatState $state) => $state->currentStep()?->finishReason());
```

### `ErrorPresenceCheck`
Stop when errors are present in the current step.
```php
use Cognesy\Addons\StepByStep\Continuation\Criteria\ErrorPresenceCheck;

$errorCheck = new ErrorPresenceCheck(
    fn(ChatState $state) => $state->currentStep()?->hasErrors() ?? false
);
```

### `RetryLimit`
Stop after a maximum number of retries on errors.
```php
use Cognesy\Addons\StepByStep\Continuation\Criteria\RetryLimit;

$retryLimit = new RetryLimit(
    2,
    fn(ChatState $state) => $state->steps(),
    fn(ChatStep $step) => $step->hasErrors()
);
```

### `ResponseContentCheck`
Stop based on response content evaluation. Useful for ending conversations when participants provide empty responses or specific content patterns.

```php
use Cognesy\Addons\StepByStep\Continuation\Criteria\ResponseContentCheck;
use Cognesy\Messages\Messages;

// Stop when response is empty
$contentCheck = new ResponseContentCheck(
    fn($state) => $state->currentStep()?->outputMessages(),
    static fn(Messages $response) => $response->last()->content()->toString() !== ''
);

// Stop when response contains specific text
$endCheck = new ResponseContentCheck(
    fn($state) => $state->currentStep()?->outputMessages(),
    static fn(Messages $response) => !str_contains($response->last()->content()->toString(), 'goodbye')
);
```

**Note**: The predicate receives a `Messages` collection, not a single `Message`. Use `->last()` to get the most recent message.

Configure in ChatConfig:

```php
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\Chat\Continuation\Criteria as ChatCriteria;

$criteria = new ContinuationCriteria(
    ChatCriteria::stepsLimit(10),
    ChatCriteria::tokenUsageLimit(4000),
);

$config = ChatConfig::default($participants)
    ->withContinuationCriteria($criteria);
```

**Default behavior**: The default configuration includes basic continuation criteria (FinishReasonCheck, StepsLimit of 16, TokenUsageLimit of 4096). All criteria must return `true` for the chat to continue.

## State Processors

State processors handle state transformation after each step using the StepByStep framework's middleware pattern. They implement `CanProcessAnyState` interface.

### Built-in State Processors

#### `AccumulateTokenUsage`
Accumulates token usage from each step into the chat state.
```php
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AccumulateTokenUsage;

$processor = new AccumulateTokenUsage();
```

#### `AppendStepMessages`
Appends step output messages to the chat state's message history. This is a core processor that ensures conversation continuity by adding each participant's response to the shared message context.

```php
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AppendStepMessages;

$processor = new AppendStepMessages();
```

**Important**: Only appends the output messages from each step to prevent duplication of input messages already in the state.

#### `MoveMessagesToBuffer`
Moves messages to a buffer when context window limits are approached.
```php
use Cognesy\Addons\StepByStep\StateProcessing\Processors\MoveMessagesToBuffer;

$processor = new MoveMessagesToBuffer();
```

#### `SummarizeBuffer`
Summarizes buffered messages to preserve context while reducing token usage.
```php
use Cognesy\Addons\StepByStep\StateProcessing\Processors\SummarizeBuffer;

$processor = new SummarizeBuffer();
```

#### `AppendContextMetadata`
Appends context metadata to the state.
```php
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AppendContextMetadata;

$processor = new AppendContextMetadata();
```

### Custom State Processors
Create custom processors by implementing `CanProcessAnyState`:

```php
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;

class MyCustomProcessor implements CanProcessAnyState
{
    public function canProcess(object $state): bool
    {
        return $state instanceof ChatState;
    }

    public function process(object $state, ?callable $next = null): object
    {
        assert($state instanceof ChatState);
        // Your custom processing logic
        $newState = $state->withMetadata(['processed' => true]);
        return $next ? $next($newState) : $newState;
    }
}
```

### Configuration
Configure state processors when creating Chat:

```php
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AccumulateTokenUsage;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AppendStepMessages;

$processors = new StateProcessors(
    new AccumulateTokenUsage(),
    new AppendStepMessages(),
    new MyCustomProcessor()
);

$chat = ChatFactory::default(
    participants: $participants,
    processors: $processors
);
```

**Default processors**: The default configuration includes `AppendStepMessages` and `AccumulateTokenUsage`.

## Observability (Events)

The Chat system provides comprehensive event monitoring throughout the conversation lifecycle. All events carry detailed data for logging and monitoring.

### Available Events

- **`ChatStepStarting`** - Fired at the beginning of each step with turn number and state
- **`ChatParticipantSelected`** - Fired when a participant is chosen with participant details
- **`ChatBeforeSend`** - Fired before sending messages to a participant
- **`ChatInferenceRequested`** - Fired when inference is requested from LLM participants
- **`ChatInferenceResponseReceived`** - Fired when inference response is received
- **`ChatToolUseStarted`** - Fired when tool use begins (for LLMParticipantWithTools)
- **`ChatToolUseCompleted`** - Fired when tool use completes
- **`ChatStepCompleted`** - Fired when a step is completed with step details
- **`ChatStateUpdated`** - Fired when chat state changes
- **`ChatCompleted`** - Fired when the chat ends with completion reason

### Event Data Examples
```php
// ChatStepStarting
['turn' => 1, 'state' => $state->toArray()]

// ChatParticipantSelected
['participantName' => 'assistant', 'participantClass' => 'LLMParticipant', 'state' => $state->toArray()]

// CollaborationCompleted
['state' => $state->toArray(), 'reason' => 'has no next turn']
```

### Event Handling
Configure event handlers when creating the Chat instance:
```php
$eventBus = new MyEventBus();
$chat = ChatFactory::default($participants, events: $eventBus);
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
use Cognesy\Addons\Chat\ChatFactory;use Cognesy\Addons\Chat\Collections\Participants;use Cognesy\Addons\Chat\Data\ChatState;use Cognesy\Addons\Chat\Participants\ScriptedParticipant;use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;

// Create test configuration with deterministic participant
$scriptedAssistant = new ScriptedParticipant(
    name: 'assistant',
    messages: ['Hello there!', 'How can I help?']
);
$participants = new Participants($scriptedAssistant);
$chat = ChatFactory::default(
    participants: $participants,
    continuationCriteria: new ContinuationCriteria(
        new StepsLimit(2, fn($state) => $state->stepCount())
    )
);

// Create initial state
$state = new ChatState();

// Execute chat turns
$state1 = $chat->nextStep($state);
$state2 = $chat->nextStep($state1);

// Verify results
expect($state2->steps()->stepCount())->toBe(2);
expect($state2->messages()->count())->toBe(2); // Two scripted responses
expect($state2->messages()->toArray()[0]['content'])->toBe('Hello there!');
expect($state2->messages()->toArray()[1]['content'])->toBe('How can I help?');
```

### Testing State Management

When testing Chat state management directly, use the `AppendStepMessages` processor:

```php
use Cognesy\Addons\Chat\Data\ChatState;use Cognesy\Addons\Chat\Data\Chat;use Cognesy\Addons\StepByStep\StateProcessing\Processors\AppendStepMessages;use Cognesy\Messages\Message;use Cognesy\Messages\Messages;

function applyStep(ChatState $state, Chat $step, AppendStepMessages $processor): ChatState {
    $stateWithStep = $state
        ->withAddedStep($step)
        ->withCurrentStep($step);

    return $processor->process($stateWithStep);
}

// Test step application
$state = new ChatState();
$processor = new AppendStepMessages();

$step = new Chat(
    participantName: 'assistant',
    outputMessages: new Messages(new Message('assistant', 'Hello!')),
    meta: []
);

$newState = applyStep($state, $step, $processor);

expect($newState->stepCount())->toBe(1);
expect($newState->messages()->count())->toBe(1);
expect($newState->currentStep())->toBe($step);
```

### Key Testing Patterns

1. **Use deterministic participants**: ScriptedParticipant or mocked LLMParticipant
2. **Set continuation criteria**: Limit turns to prevent infinite loops
3. **Verify state changes**: Check step count, message count, and content
4. **Test event emissions**: Verify events are fired correctly
5. **Mock external dependencies**: Use test doubles for inference and tools

## Architecture & Design

### Chat Class
- **Stateless** - extends StepByStep to process one step at a time
- Configured via constructor parameters with immutable components
- Safe to reuse across multiple conversations
- Thread-safe operation
- Provides `nextStep()`, `hasNextStep()`, `finalStep()`, and `iterator()` methods

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
- `StateProcessors` - Collection of state processors
- `ContinuationCriteria` - Collection of continuation criteria

### Best Practices
- Reuse `Chat` instances across conversations
- Create new `ChatState` for each conversation
- Store/restore `ChatState` for conversation persistence
- Pass updated state between `nextStep()` calls
- Use collections for type safety and immutability
- Leverage StepByStep pattern with `finalStep()` for complete conversations
- Use `iterator()` method for streaming conversation updates

## Design Principles

- **Immutability**: All core objects (`ChatState`, `ChatStep`, collections) are immutable
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
**Problem**: `nextStep()` returns immediately without any participant action
**Solution**: Ensure your configuration has participants and valid continuation criteria

### No participants available
**Problem**: "No participants available to select from" error
**Solution**: Add participants to your configuration:
```php
$participants = new Participants(new LLMParticipant(name: 'assistant'));
$chat = ChatFactory::default($participants);
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
$processors = new StateProcessors(
    new MoveMessagesToBuffer(),
    new SummarizeBuffer(),
    new AccumulateTokenUsage(),
    new AppendStepMessages()
);
```

### Tests are non-deterministic
**Problem**: Tests fail randomly due to real LLM calls
**Solution**: Use `ScriptedParticipant` or mocked `LLMParticipant` for predictable responses

### ResponseContentCheck TypeError
**Problem**: `TypeError: Argument #1 ($lastResponse) must be of type Message, Messages given`
**Solution**: Update your predicate to expect `Messages` collection, not single `Message`:

```php
// ❌ Incorrect - expects single Message
new ResponseContentCheck(
    fn($state) => $state->currentStep()?->outputMessages(),
    static fn(Message $response) => $response->content()->toString() !== ''
);

// ✅ Correct - expects Messages collection
new ResponseContentCheck(
    fn($state) => $state->currentStep()?->outputMessages(),
    static fn(Messages $response) => $response->last()->content()->toString() !== ''
);
```

### Testing processors in isolation
**Problem**: Messages aren't appearing in state during testing
**Solution**: Use the `AppendStepMessages` processor and helper function:

```php
function applyStep(ChatState $state, ChatStep $step, AppendStepMessages $processor): ChatState {
    return $processor->process(
        $state->withAddedStep($step)->withCurrentStep($step)
    );
}
```
