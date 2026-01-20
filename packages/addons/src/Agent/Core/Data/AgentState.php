<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Core\Data;

use Cognesy\Addons\Agent\Core\Collections\AgentSteps;
use Cognesy\Addons\Agent\Core\Enums\AgentStatus;
use Cognesy\Addons\Agent\Exceptions\AgentException;
use Cognesy\Addons\Agent\Core\Traits\State\HandlesAgentSteps;
use Cognesy\Addons\StepByStep\Continuation\ContinuationOutcome;
use Cognesy\Addons\StepByStep\Continuation\StopReason;
use Cognesy\Addons\StepByStep\MessageCompilation\Compilers\SelectedSections;
use Cognesy\Addons\StepByStep\Step\StepResult;
use Cognesy\Addons\StepByStep\State\Contracts\HasMessageStore;
use Cognesy\Addons\StepByStep\State\Contracts\HasMetadata;
use Cognesy\Addons\StepByStep\State\Contracts\HasStateInfo;
use Cognesy\Addons\StepByStep\State\Contracts\HasSteps;
use Cognesy\Addons\StepByStep\State\Contracts\HasUsage;
use Cognesy\Addons\StepByStep\State\Contracts\CanMarkExecutionStarted;
use Cognesy\Addons\StepByStep\State\Contracts\CanMarkStepStarted;
use Cognesy\Addons\StepByStep\State\Contracts\CanTrackExecutionTime;
use Cognesy\Addons\StepByStep\State\StateInfo;
use Cognesy\Addons\StepByStep\State\Traits\HandlesMessageStore;
use Cognesy\Addons\StepByStep\State\Traits\HandlesMetadata;
use Cognesy\Addons\StepByStep\State\Traits\HandlesStateInfo;
use Cognesy\Addons\StepByStep\State\Traits\HandlesUsage;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Data\CachedContext;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Metadata;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;

/** @implements HasSteps<AgentStep> */
final readonly class AgentState implements HasSteps, HasMessageStore, HasMetadata, HasUsage, HasStateInfo, CanMarkExecutionStarted, CanMarkStepStarted, CanTrackExecutionTime
{
    use HandlesMessageStore;
    use HandlesMetadata;
    use HandlesStateInfo;
    use HandlesAgentSteps;
    use HandlesUsage;

    private AgentStatus $status;
    private CachedContext $cache;
    /** @var StepResult[] */
    private array $stepResults;

    public string $agentId;
    public ?string $parentAgentId;
    public ?DateTimeImmutable $currentStepStartedAt;
    public ?DateTimeImmutable $executionStartedAt;

    public function __construct(
        ?AgentStatus           $status = null,
        ?AgentSteps            $steps = null,
        ?AgentStep             $currentStep = null,

        Metadata|array|null    $variables = null,
        ?Usage                 $usage = null,
        ?MessageStore          $store = null,
        ?StateInfo             $stateInfo = null,
        ?string                $agentId = null,
        ?string                $parentAgentId = null,
        ?DateTimeImmutable     $currentStepStartedAt = null,
        ?DateTimeImmutable     $executionStartedAt = null,
        ?CachedContext         $cache = null,
        ?array                 $stepResults = null,
    ) {
        $this->agentId = $agentId ?? Uuid::uuid4();
        $this->parentAgentId = $parentAgentId;
        $this->currentStepStartedAt = $currentStepStartedAt;
        $this->executionStartedAt = $executionStartedAt;

        $this->status = $status ?? AgentStatus::InProgress;
        $this->steps = $steps ?? new AgentSteps();
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
        $this->stepResults = $stepResults ?? [];
    }

    // CONSTRUCTORS ////////////////////////////////////////////

    public static function empty() : self {
        return new self();
    }

    // MUTATORS ////////////////////////////////////////////////

    public function with(
        ?AgentStatus           $status = null,
        ?AgentSteps            $steps = null,
        ?AgentStep             $currentStep = null,

        ?Metadata              $variables = null,
        ?Usage                 $usage = null,
        ?MessageStore          $store = null,
        ?StateInfo             $stateInfo = null,
        ?string                $agentId = null,
        ?string                $parentAgentId = null,
        ?DateTimeImmutable     $currentStepStartedAt = null,
        ?DateTimeImmutable     $executionStartedAt = null,
        ?CachedContext         $cache = null,
        ?array                 $stepResults = null,
    ): self {
        return new self(
            status: $status ?? $this->status,
            steps: $steps ?? $this->steps,
            currentStep: $currentStep ?? $this->currentStep,
            variables: $variables ?? $this->metadata,
            cache: $cache ?? $this->cache,
            usage: $usage ?? $this->usage,
            store: $store ?? $this->store,
            stateInfo: $stateInfo ?? $this->stateInfo->touch(),
            agentId: $agentId ?? $this->agentId,
            parentAgentId: $parentAgentId ?? $this->parentAgentId,
            currentStepStartedAt: $currentStepStartedAt ?? $this->currentStepStartedAt,
            executionStartedAt: $executionStartedAt ?? $this->executionStartedAt,
            stepResults: $stepResults ?? $this->stepResults,
        );
    }

    public function withStatus(AgentStatus $status) : self {
        return $this->with(status: $status);
    }

    public function withCurrentStepStartedAt(?DateTimeImmutable $startedAt) : self {
        return $this->with(currentStepStartedAt: $startedAt);
    }

    #[\Override]
    public function markStepStarted() : self {
        return $this->with(currentStepStartedAt: new DateTimeImmutable());
    }

    /**
     * Mark the start of a new execution (user query processing).
     * This should be called at the beginning of each execution cycle,
     * NOT at session creation. Used by ExecutionTimeLimit to prevent
     * runaway single-query processing.
     */
    #[\Override]
    public function markExecutionStarted() : self {
        return $this->with(executionStartedAt: new DateTimeImmutable());
    }

    #[\Override]
    public function withAddedExecutionTime(float $seconds) : self {
        return $this->withStateInfo(
            $this->stateInfo()->addExecutionTime($seconds),
        );
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
            ->withAddedStep($step)
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
            steps: new AgentSteps(),
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
            stepResults: [],
        );
    }

    // ACCESSORS ////////////////////////////////////////////////

    public function status() : AgentStatus {
        return $this->status;
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
     *
     * @return StepResult[]
     */
    public function stepResults(): array {
        return $this->stepResults;
    }

    /**
     * Get the last step result.
     */
    public function lastStepResult(): ?StepResult {
        if ($this->stepResults === []) {
            return null;
        }
        return $this->stepResults[array_key_last($this->stepResults)];
    }

    /**
     * Record a step result (step + continuation outcome bundled).
     */
    public function recordStepResult(StepResult $result, ?DateTimeImmutable $startedAt = null): self {
        $resolvedStartedAt = $startedAt ?? $this->currentStepStartedAt ?? new DateTimeImmutable();

        /** @var AgentStep $step */
        $step = $result->step;

        return $this
            ->withCurrentStepStartedAt($resolvedStartedAt)
            ->withAddedStep($step)
            ->withCurrentStep($step)
            ->with(stepResults: [...$this->stepResults, $result]);
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
            'steps' => array_map(static fn(AgentStep $step) => $step->toArray(), $this->steps->all()),
            'stepResults' => array_map(
                static fn(StepResult $result) => $result->toArray(static fn(object $step) => $step->toArray()),
                $this->stepResults,
            ),
        ];
    }

    public static function fromArray(array $data) : self {
        $stepResults = [];
        if (isset($data['stepResults']) && is_array($data['stepResults'])) {
            $stepResults = array_map(
                static fn(array $resultData) => StepResult::fromArray(
                    $resultData,
                    static fn(array $stepData) => AgentStep::fromArray($stepData),
                ),
                $data['stepResults'],
            );
        }

        return new self(
            status: isset($data['status']) ? AgentStatus::from($data['status']) : AgentStatus::InProgress,
            steps: isset($data['steps']) ? AgentSteps::fromArray($data['steps']) : new AgentSteps(),
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
