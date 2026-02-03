<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Data;

use Cognesy\Agents\Core\Collections\AgentSteps;
use Cognesy\Agents\Core\Collections\ErrorList;
use Cognesy\Agents\Core\Collections\StepExecutions;
use Cognesy\Agents\Core\Collections\ToolExecutions;
use Cognesy\Agents\Core\Context\AgentContext;
use Cognesy\Agents\Core\Enums\ExecutionStatus;
use Cognesy\Agents\Core\Enums\AgentStepType;
use Cognesy\Agents\Core\Stop\ExecutionContinuation;
use Cognesy\Agents\Core\Stop\StopReason;
use Cognesy\Agents\Core\Stop\StopSignal;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Metadata;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;
use Throwable;

/**
 * Agent execution state.
 *
 * Session data (always present, persists across executions):
 * - Identity: agentId, parentAgentId
 * - Session timing: createdAt, updatedAt
 * - Context: messages, metadata, system prompt, response format
 *
 * Execution data (optional, null when between executions):
 * - Execution identity: executionId
 * - Execution status and timing
 * - Step results and current step
 *
 * When execution is null, the agent is "between executions" and ready
 * for a fresh start. When present, it contains the current execution's
 * transient state (useful for mid-execution persistence/resume).
 */
final readonly class AgentState
{
    // Session data
    private string $agentId;
    private ?string $parentAgentId;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;
    private AgentContext $context;

    // Execution data - transient across executions
    private ?ExecutionState $execution;

    public function __construct(
        ?string                 $agentId = null,
        ?string                 $parentAgentId = null,
        ?DateTimeImmutable      $createdAt = null,
        ?DateTimeImmutable      $updatedAt = null,
        ?AgentContext           $context = null,
        ?ExecutionState         $execution = null,
    ) {
        $now = new DateTimeImmutable();

        // Session data
        $this->agentId = $agentId ?? Uuid::uuid4();
        $this->parentAgentId = $parentAgentId;
        $this->createdAt = $createdAt ?? $now;
        $this->updatedAt = $updatedAt ?? $this->createdAt;
        $this->context = $context ?? new AgentContext();

        // Execution data (null = between executions)
        $this->execution = $execution;
    }

    // CONSTRUCTORS ////////////////////////////////////////////

    public static function empty(): self {
        return new self();
    }

    // STATE TRANSITIONS ////////////////////////////////////////

    public function withContinuationRequestCleared() : self {
        return match(true) {
            ($this->execution === null) => $this,
            default => $this->with(execution: $this->execution->withContinuationRequestCleared()),
        };
    }

    public function withCurrentStepCompleted(?ExecutionStatus $status = null) : self {
        if ($this->execution?->currentStep() === null) {
            return $this;
        }

        $newExecution = match (true) {
            ($status === ExecutionStatus::Failed) => $this->ensureExecution()->withCurrentStepFailed(),
            $this->isFailed() => $this->ensureExecution()->withCurrentStepFailed(),
            default => $this->ensureExecution()->withCurrentStepCompleted(),
        };

        return $this->with(execution: $newExecution);
    }

    public function withExecutionCompleted(): self {
        return match(true) {
            $this->execution === null => $this->with(execution: ExecutionState::fresh()->completed()),
            $this->execution->isFailed() => $this->with(execution: $this->execution->completed(ExecutionStatus::Failed)),
            $this->execution->hasErrors() => $this->with(execution: $this->execution->completed(ExecutionStatus::Failed)),
            default => $this->with(execution: $this->execution->completed()),
        };
    }

    public function withExecutionContinued() : self {
        return $this->with(execution: $this->ensureExecution()->withContinuationRequested());
    }

    public function forNextExecution(): self {
        $store = $this->context->store()->section(AgentContext::EXECUTION_BUFFER_SECTION)->clear();

        return new self(
            agentId: $this->agentId,
            parentAgentId: $this->parentAgentId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            context: $this->context->withMessageStore($store),
            execution: null,
        );
    }

    // MUTATORS ////////////////////////////////////////////////

    public function with(
        ?string                 $agentId = null,
        ?string                 $parentAgentId = null,
        ?DateTimeImmutable      $createdAt = null,
        ?DateTimeImmutable      $updatedAt = null,
        ?AgentContext           $context = null,
        ?ExecutionState         $execution = null,
    ): self {
        return new self(
            agentId: $agentId ?? $this->agentId,
            parentAgentId: $parentAgentId ?? $this->parentAgentId,
            createdAt: $createdAt ?? $this->createdAt,
            updatedAt: $updatedAt ?? new DateTimeImmutable(),
            context: $context ?? $this->context,
            execution: $execution ?? $this->execution,
        );
    }

    public function withFailure(Throwable $error): self {
        return $this->with(execution: $this->ensureExecution()->failed($error));
    }

    public function withStopSignal(StopSignal $signal): self {
        return $this->with(execution: $this->ensureExecution()->withStopSignal($signal));
    }

    public function withCurrentStep(AgentStep $step): self {
        return $this->with(
            context: $this->context->withStepOutputRouted($step),
            execution: $this->ensureExecution()->withCurrentStep($step),
        );
    }

    public function withMessageStore(MessageStore $store): self {
        return $this->with(context: $this->context->withMessageStore($store));
    }

    public function withMessages(Messages $messages): self {
        return $this->with(context: $this->context->withMessages($messages));
    }

    public function withMetadata(string $name, mixed $value): self {
        return $this->with(context: $this->context->withMetadataKey($name, $value));
    }

    public function withUserMessage(string|Message $message): self {
        $userMessage = Message::asUser($message);
        $store = $this->context->store()
            ->section(AgentContext::DEFAULT_SECTION)
            ->appendMessages($userMessage);
        return $this->with(context: $this->context->withMessageStore($store));
    }

    public function withExecutionStatus(ExecutionStatus $status): self {
        return $this->with(execution: $this->ensureExecution()->withStatus($status));
    }

    // ACCESSORS ////////////////////////////////////

    public function agentId(): string {
        return $this->agentId;
    }

    public function parentAgentId(): ?string {
        return $this->parentAgentId;
    }

    public function createdAt(): DateTimeImmutable {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable {
        return $this->updatedAt;
    }

    public function context(): AgentContext {
        return $this->context;
    }

    public function messages(): Messages {
        return $this->context->messages();
    }

    public function store(): MessageStore {
        return $this->context->store();
    }

    public function metadata(): Metadata {
        return $this->context->metadata();
    }

    public function execution(): ?ExecutionState {
        return $this->execution;
    }

    public function status(): ?ExecutionStatus {
        return $this->execution?->status();
    }

    private function isFailed() : bool {
        return match (true) {
            $this->execution === null => false,
            $this->execution->isFailed() => true,
            default => false,
        };
    }

    public function currentStep(): ?AgentStep {
        return $this->execution?->currentStep();
    }

    public function hasCurrentStep(): bool {
        return $this->execution?->currentStep() !== null;
    }

    public function currentStepOrLast(): ?AgentStep {
        $current = $this->currentStep();
        if ($current !== null) {
            return $current;
        }
        return $this->lastStepExecution()?->step();
    }

    public function stepCount(): int {
        return $this->execution?->stepCount() ?? 0;
    }

    public function steps(): AgentSteps {
        return $this->stepExecutions()->steps();
    }

    public function stepExecutions(): StepExecutions {
        return $this->execution?->stepExecutions() ?? StepExecutions::empty();
    }

    public function lastStepExecution(): ?StepExecution {
        return $this->stepExecutions()->last();
    }

    public function lastStep(): ?AgentStep {
        return $this->lastStepExecution()?->step();
    }

    public function lastStepToolExecutions(): ToolExecutions {
        return $this->lastStepExecution()?->step()->toolExecutions() ?? ToolExecutions::none();
    }

    public function lastToolExecution(): ?ToolExecution {
        $executions = $this->lastStepToolExecutions()->all();
        return $executions !== [] ? $executions[array_key_last($executions)] : null;
    }

    public function lastStepErrors(): ErrorList {
        return $this->lastStepExecution()?->step()->errors() ?? ErrorList::empty();
    }

    public function lastStepType(): ?AgentStepType {
        return $this->lastStepExecution()?->step()->stepType();
    }

    public function lastStepUsage(): Usage {
        return $this->lastStepExecution()?->step()->usage() ?? Usage::none();
    }

    public function lastStepDuration(): ?float {
        return $this->lastStepExecution()?->duration();
    }

    public function lastStopSignal(): ?StopSignal {
        $fromStep = $this->lastStepExecution()?->continuation()->stopSignals()->first();
        if ($fromStep !== null) {
            return $fromStep;
        }
        return $this->executionContinuation()?->stopSignals()->first();
    }

    public function lastStopReason(): ?StopReason {
        return $this->lastStopSignal()?->reason;
    }

    public function lastStopSource(): ?string {
        return $this->lastStopSignal()?->source;
    }

    public function lastStepStartedAt(): ?DateTimeImmutable {
        return $this->lastStepExecution()?->startedAt();
    }

    public function lastStepCompletedAt(): ?DateTimeImmutable {
        return $this->lastStepExecution()?->completedAt();
    }

    public function hasCurrentExecution(): bool {
        return $this->execution?->currentStep() !== null;
    }

    public function shouldStop() : bool {
        return $this->execution?->shouldStop() ?? true;
    }

    public function executionContinuation(): ?ExecutionContinuation {
        return $this->ensureExecution()->continuation();
    }

    public function executionDuration(): ?float {
        return $this->execution?->totalDuration();
    }

    public function currentStepDuration(): ?float {
        return $this->execution?->currentStepDuration();
    }

    public function usage(): Usage {
        return $this->execution?->usage() ?? Usage::none();
    }

    public function hasErrors() : ?bool {
        return $this->execution?->hasErrors();
    }

    public function errors() : ErrorList {
        return $this->execution?->errors() ?? ErrorList::empty();
    }

    // DEBUG /////////////////////////////////////////////////////

    /**
     * Get a summary of the agent state for debugging.
     * This is the primary way to understand what happened during execution.
     */
    public function debug(): array
    {
        return [
            'status' => $this->status(),
            'hasExecution' => $this->execution !== null,
            'executionId' => $this->execution?->executionId(),
            'steps' => $this->stepCount(),
            'continuation' => $this->executionContinuation()?->explain() ?? '-',
            'hasErrors' => $this->hasErrors() ?? false,
            'errors' => $this->errors(),
            'usage' => $this->usage()->toArray(),
        ];
    }

    // SERIALIZATION ////////////////////////////////////////////

    public function toArray(): array {
        return [
            'agentId' => $this->agentId,
            'parentAgentId' => $this->parentAgentId,
            'createdAt' => $this->createdAt->format(DateTimeImmutable::ATOM),
            'updatedAt' => $this->updatedAt->format(DateTimeImmutable::ATOM),
            'context' => $this->context->toArray(),
            'execution' => $this->execution?->toArray(),
        ];
    }

    public static function fromArray(array $data): self {
        return new self(
            agentId: $data['agentId'] ?? null,
            parentAgentId: $data['parentAgentId'] ?? null,
            createdAt: self::parseDateFrom($data, 'createdAt'),
            updatedAt: self::parseDateFrom($data, 'updatedAt'),
            context: AgentContext::fromArray($data['context'] ?? []),
            execution: ExecutionState::fromArray($data['execution'] ?? []),
        );
    }

    // PARSING HELPERS //////////////////////////////////////////

    private static function parseDateFrom(array $data, string $key): DateTimeImmutable {
        $value = $data[$key] ?? null;
        return match (true) {
            $value instanceof DateTimeImmutable => $value,
            is_string($value) && $value !== '' => new DateTimeImmutable($value),
            default => new DateTimeImmutable(),
        };
    }

    private function ensureExecution(): ExecutionState {
        return $this->execution ?? ExecutionState::fresh();
    }
}
