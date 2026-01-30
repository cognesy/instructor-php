<?php declare(strict_types=1);

namespace Cognesy\Agents\Zero\Data;

use Cognesy\Agents\Core\Collections\AgentSteps;
use Cognesy\Agents\Core\Collections\StepExecutions;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Zero\Stop\ContinuationOutcome;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Data\CachedInferenceContext;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Metadata;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;

/**
 * Agent state combining session data with optional execution state.
 */
final readonly class AgentState
{
    private string $agentId;
    private ?string $parentAgentId;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;
    private AgentContext $context;
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

    public function withMessages(Messages $messages): self {
        return $this->with(context: $this->context->withMessages($messages));
    }

    public function withCurrentStep(AgentStep $step): self {
        if ($this->execution === null) {
            return $this->with(execution: ExecutionState::withExecutionStarted()->withCurrentStep($step));
        }
        return $this->with(execution: $this->execution->withCurrentStep($step));
    }

    // ACCESSORS ////////////////////////////////////

    public function agentId(): string {
        return $this->agentId;
    }

    public function parentAgentId(): ?string {
        return $this->parentAgentId;
    }

    public function withAddedStep(\Cognesy\Agents\Core\Data\AgentStep $step) : self {
    }

    public function createdAt(): DateTimeImmutable {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable {
        return $this->updatedAt;
    }

    public function currentExecution(): ?CurrentExecution {
        return $this->execution?->currentExecution();
    }

    public function execution(): ?ExecutionState {
        return $this->execution;
    }

    public function usage(): Usage {
        return $this->execution?->usage() ?? Usage::none();
    }

    public function currentStep(): ?AgentStep {
        return $this->execution?->currentStep();
    }

    public function messages(): Messages {
        return $this->context->messageProvider()->compile($this);
    }

    public function continuationOutcome(): ?ContinuationOutcome {
        return $this->execution?->continuationOutcome();
    }

    public function stopReason(): ?StopReason {
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
            'context' => $this->context->toArray(),
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
            context: AgentContext::fromArray($data['context'] ?? []),
            execution: self::parseExecution($data),
        );
    }

    // PARSING HELPERS //////////////////////////////////////////

    private static function parseExecution(array $data): ?ExecutionState {
        $executionData = $data['execution'] ?? null;
        return is_array($executionData) ? ExecutionState::fromArray($executionData) : null;
    }

    private static function parseMetadata(array $data): Metadata {
        $value = $data['metadata'] ?? null;
        return is_array($value) ? Metadata::fromArray($value) : new Metadata();
    }

    private static function parseCachedContext(array $data): CachedInferenceContext {
        $value = $data['cachedContext'] ?? null;
        return is_array($value) ? CachedInferenceContext::fromArray($value) : new CachedInferenceContext();
    }

    private static function parseMessageStore(array $data): MessageStore {
        $value = $data['messageStore'] ?? null;
        return is_array($value) ? MessageStore::fromArray($value) : new MessageStore();
    }

    private static function parseDateFrom(array $data, string $key): DateTimeImmutable {
        $value = $data[$key] ?? null;
        return match (true) {
            $value instanceof DateTimeImmutable => $value,
            is_string($value) && $value !== '' => new DateTimeImmutable($value),
            default => new DateTimeImmutable(),
        };
    }
}
