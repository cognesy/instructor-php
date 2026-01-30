<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Data;

use Cognesy\Agents\Core\Collections\AgentSteps;
use Cognesy\Agents\Core\Collections\StepExecutions;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Data\ContinuationOutcome;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Agents\Core\Enums\AgentStatus;
use Cognesy\Agents\Core\Exceptions\AgentException;
use Cognesy\Agents\Core\MessageCompilation\Compilers\SelectedSections;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Data\CachedInferenceContext;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Metadata;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;

/**
 * Agent state combining session data with optional execution state.
 *
 * Session data (always present, persists across executions):
 * - Identity: agentId, parentAgentId
 * - Session timing: createdAt, updatedAt
 * - Accumulated data: store, metadata, cache
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
    public const DEFAULT_SECTION = 'messages';
    public const BUFFER_SECTION = 'buffer';
    public const SUMMARY_SECTION = 'summary';
    public const EXECUTION_BUFFER_SECTION = 'execution_buffer';

    // Session data (always present)
    private string $agentId;
    private ?string $parentAgentId;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;
    private MessageStore $store;
    private Metadata $metadata;
    private CachedInferenceContext $cache;

    // Execution data (optional - null when between executions)
    private ?ExecutionState $execution;

    public function __construct(
        ?string                 $agentId = null,
        ?string                 $parentAgentId = null,
        ?DateTimeImmutable      $createdAt = null,
        ?DateTimeImmutable      $updatedAt = null,
        ?MessageStore           $store = null,
        Metadata|array|null     $variables = null,
        ?CachedInferenceContext $cache = null,
        ?ExecutionState         $execution = null,
    ) {
        $now = new DateTimeImmutable();

        // Session data
        $this->agentId = $agentId ?? Uuid::uuid4();
        $this->parentAgentId = $parentAgentId;
        $this->createdAt = $createdAt ?? $now;
        $this->updatedAt = $updatedAt ?? $this->createdAt;
        $this->store = $store ?? new MessageStore();
        $this->metadata = match (true) {
            $variables === null => new Metadata(),
            $variables instanceof Metadata => $variables,
            is_array($variables) => new Metadata($variables),
            default => new Metadata(),
        };
        $this->cache = $cache ?? new CachedInferenceContext();

        // Execution data (null = between executions)
        $this->execution = $execution;
    }

    // CONSTRUCTORS ////////////////////////////////////////////

    public static function empty(): self
    {
        return new self();
    }

    /**
     * Create a state ready for execution (with fresh ExecutionState).
     */
    public static function forExecution(): self
    {
        return (new self())->withStartedExecution();
    }

    // MUTATORS ////////////////////////////////////////////////

    public function with(
        ?string                 $agentId = null,
        ?string                 $parentAgentId = null,
        ?DateTimeImmutable      $createdAt = null,
        ?DateTimeImmutable      $updatedAt = null,
        ?MessageStore           $store = null,
        ?Metadata               $variables = null,
        ?CachedInferenceContext $cache = null,
        ?ExecutionState         $execution = null,
    ): self {
        return new self(
            agentId: $agentId ?? $this->agentId,
            parentAgentId: $parentAgentId ?? $this->parentAgentId,
            createdAt: $createdAt ?? $this->createdAt,
            updatedAt: $updatedAt ?? new DateTimeImmutable(),
            store: $store ?? $this->store,
            variables: $variables ?? $this->metadata,
            cache: $cache ?? $this->cache,
            execution: $execution ?? $this->execution,
        );
    }

    public function withStatus(AgentStatus $status): self
    {
        if ($this->execution === null) {
            return $this->with(execution: ExecutionState::withExecutionStarted()->withStatus($status));
        }
        return $this->with(execution: $this->execution->withStatus($status));
    }

    /**
     * Return copy with a fresh execution started.
     */
    public function withStartedExecution(): self
    {
        return $this->with(execution: ExecutionState::withExecutionStarted());
    }

    public function withMessageStore(MessageStore $store): self
    {
        return $this->with(store: $store);
    }

    public function withMessages(Messages $messages): self
    {
        return $this->with(store: $this->store->section(self::DEFAULT_SECTION)->setMessages($messages));
    }

    public function withMetadata(string $name, mixed $value): self
    {
        return $this->with(variables: $this->metadata->withKeyValue($name, $value));
    }

    public function withCurrentExecution(?CurrentExecution $execution): self
    {
        if ($execution === null) {
            return $this->withClearedCurrentExecution();
        }
        if ($this->execution === null) {
            return $this->with(execution: ExecutionState::withExecutionStarted()->withCurrentExecution($execution));
        }
        return $this->with(execution: $this->execution->withCurrentExecution($execution));
    }

    public function withNewStepExecution(): self
    {
        if ($this->execution === null) {
            return $this->with(execution: ExecutionState::withExecutionStarted()->withNewStepExecution());
        }
        return $this->with(execution: $this->execution->withNewStepExecution());
    }

    public function withClearedCurrentExecution(): self
    {
        if ($this->execution === null) {
            return $this;
        }
        return $this->with(execution: $this->execution->withCurrentExecutionCleared());
    }

    public function withCurrentStep(AgentStep $step): self
    {
        if ($this->execution === null) {
            return $this->with(execution: ExecutionState::withExecutionStarted()->withCurrentStep($step));
        }
        return $this->with(execution: $this->execution->withCurrentStep($step));
    }

    public function withFailure(AgentException $error): self
    {
        $failureStep = AgentStep::failure(
            inputMessages: $this->messages(),
            error: $error,
        );

        return $this
            ->withStatus(AgentStatus::Failed)
            ->withCurrentStep($failureStep);
    }

    /**
     * Add a user message to continue the conversation.
     */
    public function withUserMessage(string|Message $message, bool $resetExecutionState = true): self
    {
        $userMessage = Message::asUser($message);
        $store = $this->store->section(self::DEFAULT_SECTION)->appendMessages($userMessage);

        $state = new self(
            agentId: $this->agentId,
            parentAgentId: $this->parentAgentId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            store: $store,
            variables: $this->metadata,
            cache: $this->cache,
            execution: $resetExecutionState ? null : $this->execution,
        );

        return $resetExecutionState ? $state->forContinuation() : $state;
    }

    /**
     * Reset execution state while preserving conversation history and metadata.
     * This prepares the state for a fresh execution while keeping session data.
     */
    public function forContinuation(): self
    {
        $store = $this->store
            ->section(self::EXECUTION_BUFFER_SECTION)
            ->clear();

        return new self(
            agentId: $this->agentId,
            parentAgentId: $this->parentAgentId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            store: $store,
            variables: $this->metadata,
            cache: new CachedInferenceContext(),
            execution: null, // Clear execution state for fresh start
        );
    }

    /**
     * Record a completed step execution (step + continuation outcome bundled).
     */
    public function withStepExecutionRecorded(StepExecution $stepExecution): self
    {
        if ($this->execution === null) {
            return $this->with(execution: ExecutionState::withExecutionStarted()->withStepExecution($stepExecution));
        }
        return $this->with(execution: $this->execution->withStepExecution($stepExecution));
    }

    public function withStepExecutionReplaced(StepExecution $stepExecution): self
    {
        if ($this->execution === null) {
            return $this->with(execution: ExecutionState::withExecutionStarted()->withReplacedStepExecution($stepExecution));
        }
        return $this->with(execution: $this->execution->withReplacedStepExecution($stepExecution));
    }

    public function withCachedContext(CachedInferenceContext $cache): self
    {
        return $this->with(cache: $cache);
    }

    /**
     * Add an evaluation to the current execution (for hook-based flow control).
     * No-op if no active execution.
     */
    public function withEvaluation(ContinuationEvaluation $evaluation): self
    {
        $execution = $this->execution;
        if ($execution === null) {
            return $this;
        }

        return $this->with(execution: $execution->withEvaluation($evaluation));
    }

    /**
     * Set a precomputed continuation outcome on the current execution.
     * No-op if no active execution.
     */
    public function withContinuationOutcome(?ContinuationOutcome $outcome): self
    {
        $execution = $this->execution;
        if ($execution === null) {
            return $this;
        }

        return $this->with(execution: $execution->withPendingOutcome($outcome));
    }

    /**
     * @return list<ContinuationEvaluation>
     */
    public function evaluations(): array
    {
        return $this->execution?->pendingEvaluations() ?? [];
    }

    public function hasEvaluations(): bool
    {
        return $this->execution?->hasPendingEvaluations() ?? false;
    }

    public function pendingOutcome(): ?ContinuationOutcome
    {
        return $this->execution?->pendingOutcome();
    }

    public function withEvaluationsCleared(): self
    {
        $execution = $this->execution;
        if ($execution === null) {
            return $this;
        }

        return $this->with(execution: $execution->withEvaluationsCleared());
    }

    public function hookContext(): ?HookContext
    {
        return $this->execution?->hookContext();
    }

    public function withHookContext(HookContext $context): self
    {
        $execution = $this->execution;
        if ($execution === null) {
            return $this;
        }

        return $this->with(execution: $execution->withHookContext($context));
    }

    public function withHookContextCleared(): self
    {
        $execution = $this->execution;
        if ($execution === null) {
            return $this;
        }

        return $this->with(execution: $execution->withHookContextCleared());
    }

    // ACCESSORS ////////////////////////////////////

    /**
     * Check if there's an active execution in progress.
     */
    public function hasActiveExecution(): bool
    {
        return $this->execution !== null;
    }

    /**
     * Get the current execution state (null when between executions).
     */
    public function execution(): ?ExecutionState
    {
        return $this->execution;
    }

    public function messages(): Messages
    {
        return $this->store->section(self::DEFAULT_SECTION)->get()->messages();
    }

    public function store(): MessageStore
    {
        return $this->store;
    }

    public function metadata(): Metadata
    {
        return $this->metadata;
    }

    public function agentId(): string
    {
        return $this->agentId;
    }

    public function parentAgentId(): ?string
    {
        return $this->parentAgentId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function currentExecution(): ?CurrentExecution
    {
        return $this->execution?->currentExecution();
    }

    public function usage(): Usage
    {
        return $this->execution?->usage() ?? Usage::none();
    }

    /**
     * Get the current agent status.
     *
     * - Returns Pending when no execution is active
     * - Returns Failed if explicitly failed or stop reason is ErrorForbade
     * - Returns Completed when continuation outcome says stop
     * - Otherwise returns InProgress
     */
    public function status(): AgentStatus
    {
        if ($this->execution === null) {
            return AgentStatus::Pending;
        }

        if ($this->execution->status === AgentStatus::Failed) {
            return AgentStatus::Failed;
        }

        $outcome = $this->continuationOutcome();
        if ($outcome === null) {
            return $this->execution->status;
        }
        if ($outcome->shouldContinue()) {
            return $this->execution->status;
        }

        return match ($outcome->stopReason()) {
            StopReason::ErrorForbade => AgentStatus::Failed,
            default => AgentStatus::Completed,
        };
    }

    public function currentStep(): ?AgentStep
    {
        return $this->execution?->currentStep();
    }

    /**
     * Step count including transient current step (used during continuation evaluation).
     */
    public function transientStepCount(): int
    {
        return $this->execution?->transientStepCount() ?? 0;
    }

    /**
     * Get all completed steps (derived from step executions).
     */
    public function steps(): AgentSteps
    {
        return $this->stepExecutions()->steps();
    }

    public function stepCount(): int
    {
        return $this->execution?->stepCount() ?? 0;
    }

    public function stepAt(int $index): ?AgentStep
    {
        return $this->stepExecutions()->stepAt($index);
    }

    /** @return iterable<AgentStep> */
    public function eachStep(): iterable
    {
        return $this->stepExecutions()->steps();
    }

    public function cache(): CachedInferenceContext
    {
        return $this->cache;
    }

    public function messagesForInference(): Messages
    {
        return (new SelectedSections([
            self::SUMMARY_SECTION,
            self::BUFFER_SECTION,
            self::DEFAULT_SECTION,
            self::EXECUTION_BUFFER_SECTION,
        ]))->compile($this);
    }

    /**
     * Get all step executions.
     */
    public function stepExecutions(): StepExecutions
    {
        return $this->execution?->stepExecutions ?? StepExecutions::empty();
    }

    /**
     * Get the last step execution.
     */
    public function lastStepExecution(): ?StepExecution
    {
        return $this->stepExecutions()->last();
    }

    /**
     * Get the continuation outcome.
     *
     * Priority:
     * 1. execution->pendingOutcome (precomputed for current step)
     * 2. execution->continuationOutcome() (from last recorded step)
     */
    public function continuationOutcome(): ?ContinuationOutcome
    {
        return $this->execution?->continuationOutcome();
    }

    /**
     * Alias for continuationOutcome() for forward compatibility with SlimAgentStateSerializer.
     */
    public function lastContinuationOutcome(): ?ContinuationOutcome
    {
        return $this->continuationOutcome();
    }

    /**
     * Get the stop reason from the last step execution's continuation outcome.
     */
    public function stopReason(): ?StopReason
    {
        return $this->continuationOutcome()?->stopReason();
    }

    // DEBUG /////////////////////////////////////////////////////

    /**
     * Get a summary of the agent state for debugging.
     * This is the primary way to understand what happened during execution.
     */
    public function debug(): array
    {
        $currentStep = $this->currentStep();
        $outcome = $this->continuationOutcome();

        return [
            'status' => $this->status()->value,
            'hasExecution' => $this->execution !== null,
            'executionId' => $this->execution?->executionId,
            'steps' => $this->stepCount(),
            'stopReason' => $outcome?->stopReason()?->value,
            'resolvedBy' => $outcome?->resolvedBy(),
            'shouldContinue' => $outcome?->shouldContinue(),
            'hasErrors' => $currentStep?->hasErrors() ?? false,
            'errors' => $currentStep?->errorsAsString(),
            'finishReason' => $currentStep?->finishReason()?->value,
            'usage' => $this->usage()->toArray(),
        ];
    }

    // SERIALIZATION ////////////////////////////////////////////

    public function toArray(): array
    {
        return [
            'agentId' => $this->agentId,
            'parentAgentId' => $this->parentAgentId,
            'createdAt' => $this->createdAt->format(DateTimeImmutable::ATOM),
            'updatedAt' => $this->updatedAt->format(DateTimeImmutable::ATOM),
            'metadata' => $this->metadata->toArray(),
            'cachedContext' => $this->cache->toArray(),
            'messageStore' => $this->store->toArray(),
            'execution' => $this->execution?->toArray(),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            agentId: $data['agentId'] ?? null,
            parentAgentId: $data['parentAgentId'] ?? null,
            createdAt: self::parseDateFrom($data, 'createdAt'),
            updatedAt: self::parseDateFrom($data, 'updatedAt'),
            store: self::parseMessageStore($data),
            variables: self::parseMetadata($data),
            cache: self::parseCachedContext($data),
            execution: self::parseExecution($data),
        );
    }

    // PARSING HELPERS //////////////////////////////////////////

    private static function parseExecution(array $data): ?ExecutionState
    {
        $executionData = $data['execution'] ?? null;
        return is_array($executionData) ? ExecutionState::fromArray($executionData) : null;
    }

    private static function parseMetadata(array $data): Metadata
    {
        $value = $data['metadata'] ?? null;
        return is_array($value) ? Metadata::fromArray($value) : new Metadata();
    }

    private static function parseCachedContext(array $data): CachedInferenceContext
    {
        $value = $data['cachedContext'] ?? null;
        return is_array($value) ? CachedInferenceContext::fromArray($value) : new CachedInferenceContext();
    }

    private static function parseMessageStore(array $data): MessageStore
    {
        $value = $data['messageStore'] ?? null;
        return is_array($value) ? MessageStore::fromArray($value) : new MessageStore();
    }

    private static function parseDateFrom(array $data, string $key): DateTimeImmutable
    {
        $value = $data[$key] ?? null;
        return match (true) {
            $value instanceof DateTimeImmutable => $value,
            is_string($value) && $value !== '' => new DateTimeImmutable($value),
            default => new DateTimeImmutable(),
        };
    }
}
