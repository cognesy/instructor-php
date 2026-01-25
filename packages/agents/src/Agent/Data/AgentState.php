<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Data;

use Cognesy\Agents\Agent\Collections\AgentSteps;
use Cognesy\Agents\Agent\Collections\StepExecutions;
use Cognesy\Agents\Agent\Continuation\ContinuationOutcome;
use Cognesy\Agents\Agent\Continuation\StopReason;
use Cognesy\Agents\Agent\Enums\AgentStatus;
use Cognesy\Agents\Agent\Exceptions\AgentException;
use Cognesy\Agents\Agent\MessageCompilation\Compilers\SelectedSections;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Data\CachedContext;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Metadata;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;

final readonly class AgentState
{
    public const DEFAULT_SECTION = 'messages';

    private AgentStatus $status;
    private CachedContext $cache;
    private StepExecutions $stepExecutions;
    private ?CurrentExecution $currentExecution;
    private ?AgentStep $currentStep;
    private MessageStore $store;
    private Metadata $metadata;
    private string $agentId;
    private ?string $parentAgentId;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        ?AgentStatus $status = null,
        ?CurrentExecution $currentExecution = null,
        ?AgentStep $currentStep = null,
        Metadata|array|null $variables = null,
        ?MessageStore $store = null,
        ?string $agentId = null,
        ?string $parentAgentId = null,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
        ?CachedContext $cache = null,
        ?StepExecutions $stepExecutions = null,
    ) {
        $now = new DateTimeImmutable();
        $this->agentId = $agentId ?? Uuid::uuid4();
        $this->parentAgentId = $parentAgentId;
        $this->createdAt = $createdAt ?? $now;
        $this->updatedAt = $updatedAt ?? $this->createdAt;

        $this->status = $status ?? AgentStatus::InProgress;
        $this->currentExecution = $currentExecution;
        $this->currentStep = $currentStep ?? null;

        $this->metadata = match (true) {
            $variables === null => new Metadata(),
            $variables instanceof Metadata => $variables,
            is_array($variables) => new Metadata($variables),
            default => new Metadata(),
        };
        $this->cache = $cache ?? new CachedContext();
        $this->store = $store ?? new MessageStore();
        $this->stepExecutions = $stepExecutions ?? StepExecutions::empty();
    }

    // CONSTRUCTORS ////////////////////////////////////////////

    public static function empty(): self {
        return new self();
    }

    // MUTATORS ////////////////////////////////////////////////

    public function with(
        ?AgentStatus $status = null,
        ?CurrentExecution $currentExecution = null,
        ?AgentStep $currentStep = null,
        ?Metadata $variables = null,
        ?MessageStore $store = null,
        ?string $agentId = null,
        ?string $parentAgentId = null,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
        ?CachedContext $cache = null,
        ?StepExecutions $stepExecutions = null,
    ): self {
        return new self(
            status: $status ?? $this->status,
            currentExecution: $currentExecution ?? $this->currentExecution,
            currentStep: $currentStep ?? $this->currentStep,
            variables: $variables ?? $this->metadata,
            store: $store ?? $this->store,
            agentId: $agentId ?? $this->agentId,
            parentAgentId: $parentAgentId ?? $this->parentAgentId,
            createdAt: $createdAt ?? $this->createdAt,
            updatedAt: $updatedAt ?? new DateTimeImmutable(),
            cache: $cache ?? $this->cache,
            stepExecutions: $stepExecutions ?? $this->stepExecutions,
        );
    }

    public function withStatus(AgentStatus $status): self {
        return $this->with(status: $status);
    }

    // MESSAGE STORE METHODS ////////////////////////////////////

    public function messages(): Messages {
        return $this->store->section(self::DEFAULT_SECTION)->get()->messages();
    }

    public function store(): MessageStore {
        return $this->store;
    }

    public function withMessageStore(MessageStore $store): self {
        return $this->with(store: $store);
    }

    public function withMessages(Messages $messages): self {
        return $this->with(store: $this->store->section(self::DEFAULT_SECTION)->setMessages($messages));
    }

    // METADATA METHODS /////////////////////////////////////////

    public function metadata(): Metadata {
        return $this->metadata;
    }

    public function withMetadata(string $name, mixed $value): self {
        return $this->with(variables: $this->metadata->withKeyValue($name, $value));
    }

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

    // EXECUTION STATE METHODS ///////////////////////////////////

    public function currentExecution(): ?CurrentExecution {
        return $this->currentExecution;
    }

    public function withCurrentExecution(?CurrentExecution $execution): self {
        if ($execution === null) {
            return $this->clearCurrentExecution();
        }
        return $this->with(currentExecution: $execution);
    }

    public function beginStepExecution(): self {
        if ($this->currentExecution !== null) {
            return $this->with(currentExecution: $this->currentExecution);
        }
        $execution = new CurrentExecution(stepNumber: $this->stepCount() + 1);
        return new self(
            status: $this->status,
            currentExecution: $execution,
            currentStep: null,
            variables: $this->metadata,
            store: $this->store,
            agentId: $this->agentId,
            parentAgentId: $this->parentAgentId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            cache: $this->cache,
            stepExecutions: $this->stepExecutions,
        );
    }

    public function clearCurrentExecution(): self {
        return new self(
            status: $this->status,
            currentExecution: null,
            currentStep: $this->currentStep,
            variables: $this->metadata,
            store: $this->store,
            agentId: $this->agentId,
            parentAgentId: $this->parentAgentId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            cache: $this->cache,
            stepExecutions: $this->stepExecutions,
        );
    }

    // USAGE METHODS ////////////////////////////////////////////

    public function usage(): Usage {
        $usage = $this->stepExecutions->totalUsage();
        $currentStep = $this->currentStep;
        if ($currentStep === null) {
            return $usage;
        }
        if ($this->isCurrentStepRecorded($currentStep)) {
            return $usage;
        }
        return $usage->withAccumulated($currentStep->usage());
    }

    // STEP MUTATORS ////////////////////////////////////////////

    public function recordStep(AgentStep $step): self {
        return $this->withCurrentStep($step);
    }

    public function withCurrentStep(AgentStep $step): self {
        return $this->with(currentStep: $step);
    }

    public function failWith(AgentException $error): self {
        $failureStep = AgentStep::failure(
            inputMessages: $this->messages(),
            error: $error,
        );

        return $this
            ->withStatus(AgentStatus::Failed)
            ->recordStep($failureStep);
    }

    /**
     * Add a user message to continue the conversation.
     */
    public function withUserMessage(string|Message $message, bool $resetExecutionState = true): self {
        $userMessage = Message::asUser($message);
        $store = $this->store->section(self::DEFAULT_SECTION)->appendMessages($userMessage);
        $state = new self(
            status: AgentStatus::InProgress,
            currentExecution: null,
            currentStep: $this->currentStep,
            variables: $this->metadata,
            store: $store,
            agentId: $this->agentId,
            parentAgentId: $this->parentAgentId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            cache: $this->cache,
            stepExecutions: $this->stepExecutions,
        );

        return $resetExecutionState ? $state->forContinuation() : $state;
    }

    /**
     * Reset execution state while preserving conversation history and metadata.
     */
    public function forContinuation(): self {
        return new self(
            status: AgentStatus::InProgress,
            currentExecution: null,
            currentStep: null,
            variables: $this->metadata,
            store: $this->store,
            agentId: $this->agentId,
            parentAgentId: $this->parentAgentId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            cache: new CachedContext(),
            stepExecutions: StepExecutions::empty(),
        );
    }

    /**
     * Record a completed step execution (step + continuation outcome bundled).
     */
    public function recordStepExecution(StepExecution $execution): self {
        return new self(
            status: $this->status,
            currentExecution: null,
            currentStep: $execution->step,
            variables: $this->metadata,
            store: $this->store,
            agentId: $this->agentId,
            parentAgentId: $this->parentAgentId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            cache: $this->cache,
            stepExecutions: $this->stepExecutions->append($execution),
        );
    }

    // ACCESSORS ////////////////////////////////////////////////

    /**
     * Get the current agent status.
     *
     * Status is derived from the continuation outcome when available:
     * - If explicitly failed, returns Failed
     * - If continuation outcome says "don't continue", derives status from stop reason
     * - Otherwise returns the stored status (typically InProgress)
     *
     * This allows manual loops (hasNextStep/nextStep) to get correct final
     * status without requiring explicit finalization.
     */
    public function status(): AgentStatus {
        if ($this->status === AgentStatus::Failed) {
            return AgentStatus::Failed;
        }

        $outcome = $this->continuationOutcome();
        if ($outcome === null) {
            return $this->status;
        }
        if ($outcome->shouldContinue()) {
            return $this->status;
        }

        return match ($outcome->stopReason()) {
            StopReason::ErrorForbade => AgentStatus::Failed,
            default => AgentStatus::Completed,
        };
    }

    public function currentStep(): ?AgentStep {
        return $this->currentStep;
    }

    /**
     * Step count including transient current step (used during continuation evaluation).
     */
    public function transientStepCount(): int {
        $currentStep = $this->currentStep;
        if ($currentStep === null) {
            return $this->stepCount();
        }
        if ($this->isCurrentStepRecorded($currentStep)) {
            return $this->stepCount();
        }
        return $this->stepCount() + 1;
    }

    /**
     * Get all completed steps (derived from step executions).
     */
    public function steps(): AgentSteps {
        return $this->stepExecutions->steps();
    }

    public function stepCount(): int {
        return $this->stepExecutions->count();
    }

    public function stepAt(int $index): ?AgentStep {
        return $this->stepExecutions->stepAt($index);
    }

    /** @return iterable<AgentStep> */
    public function eachStep(): iterable {
        return $this->stepExecutions->steps();
    }

    public function cache(): CachedContext {
        return $this->cache;
    }

    public function withCachedContext(CachedContext $cache): self {
        return $this->with(cache: $cache);
    }

    public function messagesForInference(): Messages {
        return (new SelectedSections(['summary', 'buffer', self::DEFAULT_SECTION]))
            ->compile($this);
    }

    // STEP EXECUTION ACCESSORS /////////////////////////////////

    /**
     * Get all step executions.
     */
    public function stepExecutions(): StepExecutions {
        return $this->stepExecutions;
    }

    /**
     * Get the last step execution.
     */
    public function lastStepExecution(): ?StepExecution {
        return $this->stepExecutions->last();
    }

    /**
     * Get the continuation outcome from the last step execution.
     */
    public function continuationOutcome(): ?ContinuationOutcome {
        return $this->lastStepExecution()?->outcome;
    }

    /**
     * Alias for continuationOutcome() for forward compatibility with SlimAgentStateSerializer.
     */
    public function lastContinuationOutcome(): ?ContinuationOutcome {
        return $this->continuationOutcome();
    }

    /**
     * Get the stop reason from the last step execution's continuation outcome.
     */
    public function stopReason(): ?StopReason {
        return $this->continuationOutcome()?->stopReason();
    }

    // DEBUG /////////////////////////////////////////////////////

    /**
     * Get a summary of the agent state for debugging.
     * This is the primary way to understand what happened during execution.
     */
    public function debug(): array {
        $currentStep = $this->currentStep();
        $outcome = $this->continuationOutcome();

        return [
            'status' => $this->status->value,
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

    public function toArray(): array {
        return [
            'agentId' => $this->agentId,
            'parentAgentId' => $this->parentAgentId,
            'metadata' => $this->metadata->toArray(),
            'cachedContext' => $this->cache->toArray(),
            'usage' => $this->usage()->toArray(),
            'messageStore' => $this->store->toArray(),
            'stateInfo' => [
                'createdAt' => $this->createdAt->format(DateTimeImmutable::ATOM),
                'updatedAt' => $this->updatedAt->format(DateTimeImmutable::ATOM),
            ],
            'currentExecution' => $this->currentExecution?->toArray(),
            'currentStep' => $this->currentStep?->toArray(),
            'status' => $this->status->value,
            'stepExecutions' => $this->stepExecutions->toArray(),
        ];
    }

    public static function fromArray(array $data): self {
        $stepExecutionsData = $data['stepExecutions'] ?? null;
        $stepExecutions = match (true) {
            is_array($stepExecutionsData) => StepExecutions::deserialize($stepExecutionsData),
            default => StepExecutions::empty(),
        };

        $currentExecutionData = $data['currentExecution'] ?? null;
        $currentExecution = match (true) {
            is_array($currentExecutionData) => CurrentExecution::fromArray($currentExecutionData),
            default => null,
        };

        $currentStepData = $data['currentStep'] ?? null;
        $currentStep = match (true) {
            is_array($currentStepData) => AgentStep::fromArray($currentStepData),
            default => $stepExecutions->last()?->step,
        };

        $stateInfoData = $data['stateInfo'] ?? null;
        $stateInfo = match (true) {
            is_array($stateInfoData) => $stateInfoData,
            default => [],
        };
        $createdAt = self::parseDate(
            $stateInfo['createdAt'] ?? $data['createdAt'] ?? $stateInfo['startedAt'] ?? $data['startedAt'] ?? null,
        );
        $updatedAt = self::parseDate($stateInfo['updatedAt'] ?? $data['updatedAt'] ?? null, $createdAt);

        $statusValue = $data['status'] ?? null;
        $status = match (true) {
            $statusValue instanceof AgentStatus => $statusValue,
            is_string($statusValue) && $statusValue !== '' => AgentStatus::from($statusValue),
            default => AgentStatus::InProgress,
        };

        $metadataValue = $data['metadata'] ?? null;
        $metadata = match (true) {
            is_array($metadataValue) => Metadata::fromArray($metadataValue),
            default => new Metadata(),
        };

        $cachedContextValue = $data['cachedContext'] ?? null;
        $cachedContext = match (true) {
            is_array($cachedContextValue) => CachedContext::fromArray($cachedContextValue),
            default => new CachedContext(),
        };

        $storeValue = $data['messageStore'] ?? null;
        $store = match (true) {
            is_array($storeValue) => MessageStore::fromArray($storeValue),
            default => new MessageStore(),
        };

        return new self(
            status: $status,
            currentExecution: $currentExecution,
            currentStep: $currentStep,
            variables: $metadata,
            store: $store,
            agentId: $data['agentId'] ?? null,
            parentAgentId: $data['parentAgentId'] ?? null,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            cache: $cachedContext,
            stepExecutions: $stepExecutions,
        );
    }

    // INTERNAL /////////////////////////////////////////////////

    private function isCurrentStepRecorded(AgentStep $currentStep): bool {
        $lastStep = $this->stepExecutions->last()?->step;
        if ($lastStep === null) {
            return false;
        }
        return $currentStep->id() === $lastStep->id();
    }

    private static function parseDate(mixed $value, ?DateTimeImmutable $default = null): DateTimeImmutable {
        $resolvedDefault = $default ?? new DateTimeImmutable();
        return match (true) {
            $value instanceof DateTimeImmutable => $value,
            is_string($value) && $value !== '' => new DateTimeImmutable($value),
            default => $resolvedDefault,
        };
    }
}
