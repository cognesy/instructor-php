# Turn-Based Multi-Participant Chat (LLM-as-Participants)

Structured design for using multiple LLMs as distinct chat participants that take turns within a shared conversation context. Leverages existing Instructor PHP primitives (Messages V2, Script, ToolUse, ChatWithSummary-like processors) with clean boundaries and DDD-aligned components.

## Goals
- Simulate realistic chats among multiple participants (LLMs and/or humans).
- Reuse existing data structures and processors (avoid duplication).
- Keep composition explicit via `Script` and immutable `Messages V2`.
- Support tools per participant and diverse LLM providers/models.
- Provide termination policies and next-speaker policies.

## Domain Model
- Conversation (Aggregate): Owns transcript, participants, policy, pipeline, termination, and state.
- Participant (Entity): Identity, persona/instructions, LLM config, tools, memory and visibility rules.
- TurnStep (Value): One turn: inputs sent to LLM, outputs produced, tool calls, usage, finish reason, errors.
- Transcript (Value): Immutable sequence built on `Cognesy\\Messages\\V2\\Messages`.
- Policy (Strategy): Selects next speaker (round-robin, moderator-driven, reactive).
- Termination (Strategy): Decides when to stop (max turns/tokens, finish reasons, moderator stop).

## Key Data Structures
- ConversationState
  - `transcript: V2\\Messages` (public thread)
  - `summaryGlobal: string`
  - `summaryPerParticipant: array<string, string>`
  - `steps: TurnStep[]`
  - `usageTotal: Usage` and `usagePerParticipant: array<string, Usage>`
  - `variables: array` (shared context variables)
  - `status: enum { InProgress, Completed, Failed }`
- Participant
  - `id: string`, `name: string`
  - `role: MessageRole` (provider role; identity is in metadata)
  - `persona: V2\\Messages|Content` (system/developer instructions)
  - `llm: LLMProvider + model + options + OutputMode`
  - `tools: Tools` (optional)
  - `memory: { chatMax, bufferMax, summaryMax }` token budgets; cached summaries
  - `visibility: VisibilityRule` (which transcript parts are visible)
- TurnStep
  - `speakerId: string`
  - `context: V2\\Messages` (assembled Script -> Messages for this turn)
  - `output: V2\\Messages` (assistant/tool messages created this turn)
  - `toolExecutions: ToolExecutions`
  - `usage: Usage`
  - `finishReason: InferenceFinishReason|null`
  - `errors: Throwable[]`

## Message Addressing
Use `V2\\Message->metadata` to track conversation semantics without breaking provider roles:
- `from: string` (participantId)
- `to: array<string>|'all'` (recipient(s))
- `channel: 'public'|'private'`
Keep provider roles via `MessageRole` (System/Developer/User/Assistant/Tool) for API compatibility.

## Prompt Composition (Script + Sections)
Reuse `Cognesy\\Template\\Script\\Script` to assemble per-turn inputs. Suggested sections:
- `persona/<participantId>`: system/developer instructions for the current speaker.
- `summary/global`: rolling global summary (short context).
- `summary/<participantId>`: speaker-specific memory/summary.
- `buffer/global`: overflow messages not in the main transcript.
- `transcript`: visible subset of the conversation (projected by visibility rules).
- Optional `tools/<participantId>`: hints/schema previews for tools.
The engine selects appropriate sections and renders to `V2\\Messages` per turn.

## Pipelines and Processors
Build a `ConversationPipeline` on top of `ScriptPipeline`, reusing/adding processors:
- MoveTranscriptToBuffer: like `MoveMessagesToBuffer`, operate on `transcript` section.
- SummarizeTranscript: like `SummarizeBuffer`, write to `summary/global` and clear `buffer`.
- UpdateParticipantMemory: summarize the speaker-visible transcript to `summary/<id>`.
- AppendAddressedMessages: ensure DMs or addressed messages route into the speaker’s view.
Where possible, adapt existing processors `MoveMessagesToBuffer` and `SummarizeBuffer`.

## Execution Flow
- ConversationEngine
  1. Select next speaker via Policy.
  2. Build per-turn Script (sections above) -> `V2\\Messages` context.
  3. Choose responder:
     - With tools: use `ToolUse` (driver `ToolCallingDriver`) for this participant.
     - Without tools: use `Inference` (Polyglot) directly.
  4. Convert outputs/tool-calls into `V2\\Messages` tagged with `metadata.from = speakerId`.
  5. Append to transcript; accumulate usage; emit `TurnStep`.
  6. Run pipeline (buffering/summarization/memory updates).
  7. Check termination criteria; loop.

## Policies (Next Speaker)
- RoundRobinPolicy(order, startWithId)
- ModeratorPolicy (a moderator LLM selects next speaker)
- ReactivePolicy (mentions/DMs influence next speaker selection)

## Termination (Stopping Rules)
Reuse/adapt ToolUse continuation criteria:
- StepsLimit, TokenUsageLimit, ExecutionTimeLimit, RetryLimit
- ErrorPresenceCheck (stop on error), FinishReasonCheck (respect provider finish reasons)
- Custom: MaxTurns, FinishOnPhrase (e.g., “END”), ModeratorStop

## Reuse of Existing Components
- Messages: Prefer `Cognesy\\Messages\\V2` for immutability and fluent transforms.
- Script: Clear, explicit prompt assembly and section selection.
- ToolUse: For tool-enabled participants; avoids duplicating tool logic.
- Tokenization: `Utils\\Tokenizer` and ChatWithSummary-like buffering.
- Inference: `Polyglot` (LLMProvider, model, options, OutputMode) per participant.

## Proposed API (Outline)
- Conversation (aggregate)
  - `static create(Participant ...$participants): self`
  - `withTranscript(V2\\Messages $messages): self`
  - `withPolicy(CanSelectNextSpeaker $policy): self`
  - `withTermination(CanDecideToContinue ...$criteria): self`
  - `withPipeline(ScriptPipeline $pipeline): self`
- ConversationEngine (service)
  - `static from(Conversation $conv, ?ScriptPipeline $pipeline = null): self`
  - `nextTurn(): TurnStep`
  - `run(): TurnStep` (until termination)
  - `iterator(): iterable<TurnStep>`
- Participant (entity builder)
  - `static create(string $id, string $name): self`
  - `llm(LLMProvider $llm, string $model, array $options = [], OutputMode $mode = OutputMode::Text|Tools): self`
  - `withTools(Tools $tools): self`
  - `withPersona(V2\\Messages|Content|string $persona): self`
  - `withMemoryBudgets(int $chatMax, int $bufferMax, int $summaryMax): self`
  - `withVisibility(VisibilityRule $rule): self`

## Minimal Class Layout (Packages)
- `packages/addons/src/Conversation/Conversation.php`
- `packages/addons/src/Conversation/ConversationState.php`
- `packages/addons/src/Conversation/ConversationEngine.php`
- `packages/addons/src/Conversation/Participant.php`
- `packages/addons/src/Conversation/TurnStep.php`
- `packages/addons/src/Conversation/Policies/*.php`
- `packages/addons/src/Conversation/Termination/*.php`
- `packages/addons/src/Conversation/Pipeline/*.php` (adapters around `ScriptPipeline`)

## Example (Pseudo)
```php
use Cognesy\Messages\V2\Messages as V2Messages;

$alice = Participant::create('alice', 'Alice')
  ->llm($openai, 'gpt-4o')
  ->withTools($tools)
  ->withPersona('You are a helpful sales agent...');

$bob = Participant::create('bob', 'Bob')
  ->llm($xai, 'grok-2')
  ->withPersona('You are a skeptical customer...');

$seed = V2Messages::fromArray([
  ['role' => 'system', 'content' => 'Simulate a customer support chat.'],
  ['role' => 'user', 'content' => 'Hi, I have a question about pricing.'],
]);

$conv = Conversation::create($alice, $bob)
  ->withTranscript($seed)
  ->withPolicy(new RoundRobinPolicy(startWith: 'alice'))
  ->withTermination(new StepsLimit(6));

$engine = ConversationEngine::from($conv);
foreach ($engine->iterator() as $turn) {
  // inspect $turn, stream $turn->output(), etc.
}
```

## Clean Architecture Notes
- Entities are simple; domain behavior lives in services (Engine, Pipelines, Policies).
- Immutability (`V2\\Messages`) makes transformations predictable and testable.
- Side effects (LLM calls) are isolated in a Responder/Driver, enabling unit tests.
- Strategies (Policy/Termination) keep extension open, implementation closed.
- Metadata carries participant identity and routing without breaking provider APIs.

## Interoperability
- Compose ChatWithSummary-like processors in the conversation pipeline.
- Use ToolUse end-to-end for tool-enabled participants.
- Adapt v1 `Messages` at boundaries if needed; prefer `V2` internally.

