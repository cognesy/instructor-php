
# Chat Component

Chat orchestrates multi‑turn, multi‑participant conversations with modular context management (buffering, summarization, trimming) and pluggable continuation criteria. It is designed to integrate with ToolUse and future Agent components while keeping the core focused on running arbitrarily long chat sequences.

This document explains how to use Chat, customize its behavior, and test it deterministically.

## Overview

- Orchestrator: `Cognesy\Addons\Chat\Chat`
- State: `Data\ChatState` (script, participants, variables, usage, steps, timestamps)
- Step: `Data\ChatStep` (participant id, messages, usage, optional inference response)
- Participants (`Contracts\CanParticipateInChat`):
  - `Participants\HumanParticipant` — user input via callback or programmatic append
  - `Participants\LLMParticipant` — assistant using Polyglot Inference
  - `Participants\LLMWithToolsParticipant` — assistant backed by ToolUse
- Participant selection (`Contracts\CanChooseNextParticipant`):
  - `Selectors\RoundRobinSelector` — default round‑robin
  - `Selectors\LLMBasedCoordinator` — choose next via Inference prompt
  - `Selectors\ToolBasedCoordinator` — choose next via ToolUse
- Processors
  - Step processors (`Contracts\CanProcessChatStep`): accumulate usage, update step, etc.
  - Script processors (`Contracts\ScriptProcessor`): buffer/summarize/trim/rotate message sections
  - Message processors (`Contracts\CanProcessMessage`): pre‑send and pre‑append hooks
- Continuation criteria (`Contracts\CanDecideToContinue`): steps/time/tokens/finish‑reason
- Observability: simple `$data` events across lifecycle

## Quick Start (Human → Assistant)

```php
use Cognesy\Addons\Chat\Chat;
use Cognesy\Addons\Chat\Participants\LLMParticipant;
use Cognesy\Addons\Chat\Selectors\RoundRobinSelector;
use Cognesy\Messages\Messages;

$chat = new Chat(selector: new RoundRobinSelector());
$chat->withParticipants([
    new LLMParticipant(id: 'assistant', model: 'gpt-4o-mini'),
]);

$chat->withMessages(Messages::fromString('Hello', 'user'));
$step = $chat->nextTurn();
print $step->messages()->toString();
```

Notes:
- Append user messages via `withMessages(...)`; call `nextTurn()` for one assistant reply.
- Use `finalTurn()` or `iterator()` if you rely on continuation criteria for multi‑turn flows.

## Quick Start (Chat with Summary)

```php
use Cognesy\Addons\Chat\Pipelines\BuildChatWithSummary;
use Cognesy\Addons\Chat\Utils\SummarizeMessages;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\LLMProvider;

$summarizer = new SummarizeMessages(llm: LLMProvider::using('openai'), tokenLimit: 1024);

$chat = BuildChatWithSummary::create(
    maxChatTokens: 256,
    maxBufferTokens: 256,
    maxSummaryTokens: 1024,
    summarizer: $summarizer,
    model: 'gpt-4o-mini',
);

// optional persistent sections
$script = $chat->state()->script()
    ->withSectionMessages('system', Messages::fromString('You are helpful.', 'system'))
    ->withSectionMessages('context', Messages::fromString('Domain context...', 'system'));
$state = $chat->state();
$state->withScript($script);
$chat->withState($state);

$chat->withMessages(Messages::fromString('First question', 'user'));
$chat->nextTurn();
```

## Participants

- `HumanParticipant(id: 'user', messageProvider: ?callable)`
  - If a callback is provided, it is invoked each turn to fetch the user message (`fn(ChatState): string|array|Messages`).
  - If omitted, append user messages programmatically via `Chat::withMessages(...)`.

- `LLMParticipant(id: 'assistant', inference?: Inference, model?: string, options?: array, sectionOrder?: array, llmProvider?: LLMProvider, llmPreset?: string)`
  - Uses Polyglot Inference to generate assistant replies from the selected sections (default: `['summary','buffer','main']`).
  - You can inject a custom `Inference` (e.g., with a mock driver), an `LLMProvider`, or a preset via `llmPreset`.

- `LLMWithToolsParticipant(id: 'assistant-tools', toolUse?: ToolUse, toolUseFactory?: callable)`
  - Delegates to a `ToolUse` session to produce the assistant messages; accepts a ready instance or a factory.

## Participant Selection

- `RoundRobinSelector` — cycles through participants in order.
- `LLMBasedCoordinator` — prompts an LLM to return the next participant id; inject an `Inference` for deterministic tests.
- `ToolBasedCoordinator` — uses a `ToolUse` instance to decide the next participant id.

Assign a selector:

```php
$chat->withSelector(new RoundRobinSelector());
```

## Continuation Criteria

Defaults (applied if none provided):
- `StepsLimit($maxSteps = 1)`
- `TokenUsageLimit($maxTokens = 8192)`
- `ExecutionTimeLimit($seconds = 30)`
- `FinishReasonCheck($finishReasons = [])`

Customize:

```php
use Cognesy\Addons\Chat\ContinuationCriteria\StepsLimit;
$chat->withDefaultContinuationCriteria(maxSteps: 3);
// or
$chat->withContinuationCriteria(new StepsLimit(10));
```

## Processors

- Step processors
  - Defaults: `AccumulateTokenUsage`, `UpdateStep`.
  - Add custom: `$chat->withProcessors(new MyStepProcessor());`

- Script processors
  - Purpose: context policies such as buffering, summarizing, trimming, rotating.
  - Attach: `$chat->withScriptProcessors(new MoveMessagesToBuffer(...), new SummarizeBuffer(...));`

- Message processors
  - Hooks: `beforeSend(Messages, ChatState)` and `beforeAppend(Messages, ChatState)`.
  - Attach: `$chat->withMessageProcessors(new MyMessageProcessor());`

## Observability (Events)

All Chat events are simple classes extending `Cognesy\Events\Event` and carry a `$data` array payload for logs/monitoring. Examples:
- `ChatTurnStarting(['state' => ..., 'turn' => ...])`
- `ChatParticipantSelected(['state' => ..., 'participantId' => ...])`
- `ChatBeforeSend(['state' => ..., 'messages' => ...])`
- `ChatContextTransformed(['state' => ..., 'before' => ..., 'after' => ...])`
- `ChatTurnCompleted(['state' => ..., 'step' => ...])`
- `ChatCompleted(['state' => ..., 'reason' => ...])`

Tip: add fingerprints (e.g., short content hashes) in events when debugging complex context pipelines.

## Testing

Deterministic patterns (no real LLM/network):
- Inject `FakeInferenceDriver` into an `Inference` and pass it to `LLMParticipant`.
- For coordinator tests: inject `Inference` into `LLMBasedCoordinator` or a `ToolUse` with a fake driver into `ToolBasedCoordinator`.

Example (participants):

```php
use Cognesy\Polyglot\Inference\Inference;use Cognesy\Polyglot\Inference\Data\InferenceResponse;use Tests\Addons\Support\FakeInferenceDriver;

$driver = new FakeInferenceDriver([ new InferenceResponse(content: 'hi!') ]);
$inference = (new Inference())->withLLMProvider(\Cognesy\Polyglot\Inference\LLMProvider::new()->withDriver($driver));
$assistant = new LLMParticipant(id: 'assistant', inference: $inference);
```

Feature example (flow):
- Append user → `nextTurn()` → assert alternating user/assistant messages and expected contents in the `main` section.

## State & Reuse

- `Chat` is stateful; it holds a `ChatState` accumulating script, steps, usage, etc.
- Preferred: new `Chat` per run; if reusing, call `withState(new ChatState(...))` and reapply participants/processors as needed.
- Participants and selectors are configuration objects and safe to reuse across runs.

## Design Notes

- Immutability: `Script`, `Section`, `Messages`, and `Message` are immutable value objects; Chat operations create copies.
- Separation of concerns: participants generate content; orchestrator manages sequence; processors implement policies; selectors coordinate turns.
- YAGNI: advanced context strategies (multi‑stage buffers, offload/retrieve) can be implemented via additional `ScriptProcessor` classes without changing the orchestrator.

## Troubleshooting

- No replies? Ensure you either append user messages or provide a `HumanParticipant` callback.
- Context keeps growing? Add `MoveMessagesToBuffer` and `SummarizeBuffer` with appropriate thresholds.
- Need deterministic tests? Use `FakeInferenceDriver` and avoid network calls.
