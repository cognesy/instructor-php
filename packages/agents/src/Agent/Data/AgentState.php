<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Data;

use Cognesy\Agents\Agent\Collections\AgentSteps;
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
    private StepResults $stepResults;
    private ?AgentStep $currentStep;
    private MessageStore $store;
    private Metadata $metadata;
    private StateInfo $stateInfo;
    private Usage $usage;

    public string $agentId;
    public ?string $parentAgentId;
    public ?DateTimeImmutable $currentStepStartedAt;
    public ?DateTimeImmutable $executionStartedAt;

    public function __construct(
        ?AgentStatus        $status = null,
        ?AgentStep          $currentStep = null,

        Metadata|array|null $variables = null,
        ?Usage              $usage = null,
        ?MessageStore       $store = null,
        ?StateInfo          $stateInfo = null,
        ?string             $agentId = null,
        ?string             $parentAgentId = null,
        ?DateTimeImmutable  $currentStepStartedAt = null,
        ?DateTimeImmutable  $executionStartedAt = null,
        ?CachedContext      $cache = null,
        ?StepResults        $stepResults = null,
    ) {
        $this->agentId = $agentId ?? Uuid::uuid4();
        $this->parentAgentId = $parentAgentId;
        $this->currentStepStartedAt = $currentStepStartedAt;
        $this->executionStartedAt = $executionStartedAt;

        $this->status = $status ?? AgentStatus::InProgress;
        $this->currentStep = $currentStep ?? null;

        $this->stateInfo = $stateInfo ?? StateInfo::new();
        $this->metadata = match(true) {
            $variables === null => new Metadata(),
            $variables instanceof Metadata => $variables,
            is_array($variables) => new Metadata($variables),
            default => new Metadata(),
        };
        $this->cache = $cache ?? new CachedContext();
        $this->usage = $usage ?? new Usage();
        $this->store = $store ?? new MessageStore();
        $this->stepResults = $stepResults ?? StepResults::empty();
    }

    // CONSTRUCTORS ////////////////////////////////////////////

    public static function empty() : self {
        return new self();
    }

    // MUTATORS ////////////////////////////////////////////////

    public function with(
        ?AgentStatus       $status = null,
        ?AgentStep         $currentStep = null,
        ?Metadata          $variables = null,
        ?Usage             $usage = null,
        ?MessageStore      $store = null,
        ?StateInfo         $stateInfo = null,
        ?string            $agentId = null,
        ?string            $parentAgentId = null,
        ?DateTimeImmutable $currentStepStartedAt = null,
        ?DateTimeImmutable $executionStartedAt = null,
        ?CachedContext     $cache = null,
        ?StepResults       $stepResults = null,
    ): self {
        return new self(
            status: $status ?? $this->status,
            currentStep: $currentStep ?? $this->currentStep,
            variables: $variables ?? $this->metadata,
            usage: $usage ?? $this->usage,
            store: $store ?? $this->store,
            stateInfo: $stateInfo ?? $this->stateInfo->touch(),
            agentId: $agentId ?? $this->agentId,
            parentAgentId: $parentAgentId ?? $this->parentAgentId,
            currentStepStartedAt: $currentStepStartedAt ?? $this->currentStepStartedAt,
            executionStartedAt: $executionStartedAt ?? $this->executionStartedAt,
            cache: $cache ?? $this->cache,
            stepResults: $stepResults ?? $this->stepResults,
        );
    }

    public function withStatus(AgentStatus $status) : self {
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

    // STATE INFO METHODS ///////////////////////////////////////

    public function stateInfo(): StateInfo {
        return $this->stateInfo;
    }

    public function withStateInfo(StateInfo $stateInfo): self {
        return $this->with(stateInfo: $stateInfo);
    }

    public function id(): string {
        return $this->stateInfo->id();
    }

    public function startedAt(): DateTimeImmutable {
        return $this->stateInfo->startedAt();
    }

    public function updatedAt(): DateTimeImmutable {
        return $this->stateInfo->updatedAt();
    }

    // USAGE METHODS ////////////////////////////////////////////

    public function usage(): Usage {
        return $this->usage;
    }

    public function withUsage(Usage $usage): self {
        return $this->with(usage: $usage);
    }

    public function withAccumulatedUsage(Usage $usage): self {
        return $this->withUsage($this->usage->withAccumulated($usage));
    }

    // EXECUTION TIME METHODS ///////////////////////////////////

    public function withCurrentStepStartedAt(?DateTimeImmutable $startedAt) : self {
        return $this->with(currentStepStartedAt: $startedAt);
    }

    public function markStepStarted() : self {
        return $this->with(currentStepStartedAt: new DateTimeImmutable());
    }

    /**
     * Mark the start of a new execution (user query processing).
     * This should be called at the beginning of each execution cycle,
     * NOT at session creation. Used by ExecutionTimeLimit to prevent
     * runaway single-query processing.
     */
    public function markExecutionStarted() : self {
        return $this->with(executionStartedAt: new DateTimeImmutable());
    }

    public function withAddedExecutionTime(float $seconds) : self {
        return $this->withStateInfo(
            $this->stateInfo()->addExecutionTime($seconds),
        );
    }

    /**
     * Track execution time for the current step and clear the step start timestamp.
     */
    public function markStepCompleted() : self {
        if ($this->currentStepStartedAt === null) {
            return $this;
        }
        $started = (float) $this->currentStepStartedAt->format('U.u');
        $duration = microtime(true) - $started;
        return $this->withAddedExecutionTime($duration);
    }

    public function recordStep(AgentStep $step, ?DateTimeImmutable $startedAt = null) : self {
        $resolvedStartedAt = $startedAt;
        if ($resolvedStartedAt === null) {
            $resolvedStartedAt = $this->currentStepStartedAt;
        }
        if ($resolvedStartedAt === null) {
            $resolvedStartedAt = new DateTimeImmutable();
        }

        return $this
            ->withCurrentStepStartedAt($resolvedStartedAt)
            ->withCurrentStep($step);
    }

    public function failWith(AgentException $error) : self {
        $failureStep = AgentStep::failure(
            inputMessages: $this->messages(),
            error: $error,
        );

        return $this
            ->withStatus(AgentStatus::Failed)
            ->recordStep($failureStep);
    }

    /**
     * Get the timestamp when the current execution started.
     * Returns null if execution hasn't started yet (e.g., after deserialization).
     */
    public function executionStartedAt(): ?DateTimeImmutable {
        return $this->executionStartedAt;
    }

    /**
     * Add a user message to continue the conversation.
     */
    public function withUserMessage(string|Message $message, bool $resetExecutionState = true): self {
        $userMessage = Message::asUser($message);
        $store = $this->store->section(self::DEFAULT_SECTION)->appendMessages($userMessage);
        $state = $this->with(
            store: $store,
            status: AgentStatus::InProgress,
        );

        return $resetExecutionState ? $state->forContinuation() : $state;
    }

    /**
     * Reset execution state while preserving conversation history and metadata.
     */
    public function forContinuation(): self {
        return new self(
            status: AgentStatus::InProgress,
            currentStep: null,
            variables: $this->metadata,
            usage: new Usage(),
            store: $this->store,
            stateInfo: $this->stateInfo->touch(),
            agentId: $this->agentId,
            parentAgentId: $this->parentAgentId,
            currentStepStartedAt: null,
            executionStartedAt: null,
            cache: new CachedContext(),
            stepResults: StepResults::empty(),
        );
    }

    // STEP MUTATORS ////////////////////////////////////////////

    public function withCurrentStep(AgentStep $step): self {
        return $this->with(currentStep: $step);
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
    public function status() : AgentStatus {
        // Already explicitly failed - respect that
        if ($this->status === AgentStatus::Failed) {
            return AgentStatus::Failed;
        }

        // Derive from continuation outcome if execution has stopped
        $outcome = $this->continuationOutcome();
        if ($outcome !== null && !$outcome->shouldContinue()) {
            return $outcome->stopReason() === StopReason::ErrorForbade
                ? AgentStatus::Failed
                : AgentStatus::Completed;
        }

        return $this->status;
    }

    // STEP ACCESSORS ///////////////////////////////////////////

    public function currentStep() : ?AgentStep {
        return $this->currentStep;
    }

    /**
     * Get all completed steps (derived from step results).
     */
    public function steps() : AgentSteps {
        return $this->stepResults->steps();
    }

    public function stepCount() : int {
        return $this->stepResults->count();
    }

    public function stepAt(int $index): ?AgentStep {
        return $this->stepResults->stepAt($index);
    }

    /** @return iterable<AgentStep> */
    public function eachStep(): iterable {
        return $this->stepResults->steps();
    }

    public function cache() : CachedContext {
        return $this->cache;
    }

    public function withCachedContext(CachedContext $cache) : self {
        return $this->with(cache: $cache);
    }

    public function messagesForInference(): Messages {
        return (new SelectedSections(['summary', 'buffer', self::DEFAULT_SECTION]))
            ->compile($this);
    }

    // STEP RESULT ACCESSORS ////////////////////////////////////

    /**
     * Get all step results.
     */
    public function stepResults(): StepResults {
        return $this->stepResults;
    }

    /**
     * Get the last step result.
     */
    public function lastStepResult(): ?StepResult {
        return $this->stepResults->last();
    }

    /**
     * Record a step result (step + continuation outcome bundled).
     */
    public function recordStepResult(StepResult $result): self {
        return $this
            ->withCurrentStep($result->step)
            ->with(stepResults: $this->stepResults->append($result));
    }

    /**
     * Get the continuation outcome from the last step result.
     */
    public function continuationOutcome(): ?ContinuationOutcome {
        return $this->lastStepResult()?->outcome;
    }

    /**
     * Alias for continuationOutcome() for forward compatibility with SlimAgentStateSerializer.
     */
    public function lastContinuationOutcome(): ?ContinuationOutcome {
        return $this->continuationOutcome();
    }

    /**
     * Get the stop reason from the last step result's continuation outcome.
     */
    public function stopReason(): ?StopReason {
        return $this->continuationOutcome()?->stopReason();
    }

    // DEBUG ////////////////////////////////////////////////

    /**
     * Get a summary of the agent state for debugging.
     * This is the primary way to understand what happened during execution.
     */
    public function debug() : array {
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
            'usage' => $this->usage->toArray(),
        ];
    }

    // SERIALIZATION ////////////////////////////////////////

    public function toArray() : array {
        return [
            'agentId' => $this->agentId,
            'parentAgentId' => $this->parentAgentId,
            'currentStepStartedAt' => $this->currentStepStartedAt?->format(DATE_ATOM),
            'executionStartedAt' => $this->executionStartedAt?->format(DATE_ATOM), // For debugging only - not restored
            'metadata' => $this->metadata->toArray(),
            'cachedContext' => $this->cacheToArray($this->cache),
            'usage' => $this->usage->toArray(),
            'messageStore' => $this->store->toArray(),
            'stateInfo' => $this->stateInfo->toArray(),
            'currentStep' => $this->currentStep?->toArray(),
            'status' => $this->status->value,
            'stepResults' => $this->stepResults->toArray(),
        ];
    }

    public static function fromArray(array $data) : self {
        $stepResults = isset($data['stepResults']) && is_array($data['stepResults'])
            ? StepResults::deserialize($data['stepResults'])
            : StepResults::empty();

        return new self(
            status: isset($data['status']) ? AgentStatus::from($data['status']) : AgentStatus::InProgress,
            currentStep: isset($data['currentStep']) ? AgentStep::fromArray($data['currentStep']) : null,

            variables: isset($data['metadata']) ? Metadata::fromArray($data['metadata']) : new Metadata(),
            cache: self::cacheFromArray($data),
            usage: isset($data['usage']) ? Usage::fromArray($data['usage']) : new Usage(),
            store: isset($data['messageStore']) ? MessageStore::fromArray($data['messageStore']) : new MessageStore(),
            stateInfo: isset($data['stateInfo']) ? StateInfo::fromArray($data['stateInfo']) : null,
            agentId: $data['agentId'] ?? null,
            parentAgentId: $data['parentAgentId'] ?? null,
            currentStepStartedAt: isset($data['currentStepStartedAt']) ? new DateTimeImmutable($data['currentStepStartedAt']) : null,
            // NOTE: executionStartedAt is intentionally NOT restored from serialization.
            // Each new execution should start fresh - markExecutionStarted() will be called
            // when the execution begins. This prevents timeouts in multi-turn conversations.
            executionStartedAt: null,
            stepResults: $stepResults,
        );
    }

    // INTERNAL ////////////////////////////////////////////////

    private function cacheToArray(CachedContext $cache) : array {
        $responseFormat = $cache->responseFormat();
        $responseFormatData = $responseFormat->isEmpty()
            ? []
            : [
                'type' => $responseFormat->type(),
                'schema' => $responseFormat->schema(),
                'name' => $responseFormat->schemaName(),
                'strict' => $responseFormat->strict(),
            ];

        return [
            'messages' => $cache->messages()->toArray(),
            'tools' => $cache->tools(),
            'toolChoice' => $cache->toolChoice(),
            'responseFormat' => $responseFormatData,
        ];
    }

    private static function cacheFromArray(array $data) : CachedContext {
        $cacheData = match (true) {
            isset($data['cachedContext']) && is_array($data['cachedContext']) => $data['cachedContext'],
            isset($data['cache']) && is_array($data['cache']) => $data['cache'],
            default => [],
        };

        if ($cacheData === []) {
            return new CachedContext();
        }

        $messages = $cacheData['messages'] ?? [];
        $tools = $cacheData['tools'] ?? [];
        $toolChoice = $cacheData['toolChoice'] ?? $cacheData['tool_choice'] ?? [];
        $responseFormat = $cacheData['responseFormat'] ?? $cacheData['response_format'] ?? null;

        $normalizedResponseFormat = match (true) {
            is_array($responseFormat) && $responseFormat !== [] => $responseFormat,
            default => null,
        };

        return new CachedContext(
            messages: $messages,
            tools: $tools,
            toolChoice: $toolChoice,
            responseFormat: $normalizedResponseFormat,
        );
    }
}
