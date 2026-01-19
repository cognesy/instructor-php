<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Data;

use Cognesy\Addons\Chat\Collections\ChatSteps;
use Cognesy\Addons\Chat\State\HandlesChatSteps;
use Cognesy\Addons\StepByStep\Continuation\ContinuationOutcome;
use Cognesy\Addons\StepByStep\Continuation\StopReason;
use Cognesy\Addons\StepByStep\State\Contracts\HasMessageStore;
use Cognesy\Addons\StepByStep\State\Contracts\HasMetadata;
use Cognesy\Addons\StepByStep\State\Contracts\HasStateInfo;
use Cognesy\Addons\StepByStep\State\Contracts\HasSteps;
use Cognesy\Addons\StepByStep\State\Contracts\HasUsage;
use Cognesy\Addons\StepByStep\State\StateInfo;
use Cognesy\Addons\StepByStep\State\Traits\HandlesMessageStore;
use Cognesy\Addons\StepByStep\State\Traits\HandlesMetadata;
use Cognesy\Addons\StepByStep\State\Traits\HandlesStateInfo;
use Cognesy\Addons\StepByStep\State\Traits\HandlesUsage;
use Cognesy\Addons\StepByStep\Step\StepResult;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Metadata;

/** @implements HasSteps<ChatStep> */
final readonly class ChatState implements HasSteps, HasMetadata, HasMessageStore, HasUsage, HasStateInfo
{
    use HandlesChatSteps;
    use HandlesMessageStore;
    use HandlesMetadata;
    use HandlesStateInfo;
    use HandlesUsage;

    /** @var StepResult[] */
    private array $stepResults;

    public function __construct(
        ?ChatSteps          $steps = null,
        ?ChatStep           $currentStep = null,
        Metadata|array|null $variables = null,
        ?Usage              $usage = null,
        ?MessageStore       $store = null,
        ?StateInfo          $stateInfo = null,
        ?array              $stepResults = null,
    ) {
        $this->steps = $steps ?? new ChatSteps();
        $this->currentStep = $currentStep;

        $this->stateInfo = $stateInfo ?? StateInfo::new();
        $this->metadata = match(true) {
            $variables === null => new Metadata(),
            $variables instanceof Metadata => $variables,
            is_array($variables) => new Metadata($variables),
            default => new Metadata(),
        };
        $this->usage = $usage ?? new Usage();
        $this->store = $store ?? new MessageStore();
        $this->stepResults = $stepResults ?? [];
    }

    // MUTATORS /////////////////////////////////////////////////

    public function with(
        ?ChatSteps    $steps = null,
        ?ChatStep     $currentStep = null,
        ?Metadata     $variables = null,
        ?Usage        $usage = null,
        ?MessageStore $store = null,
        ?StateInfo    $stateInfo = null,
        ?array        $stepResults = null,
    ): self {
        return new self(
            steps: $steps ?? $this->steps,
            currentStep: $currentStep ?? $this->currentStep,
            variables: $variables ?? $this->metadata,
            usage: $usage ?? $this->usage,
            store: $store ?? $this->store,
            stateInfo: $stateInfo ?? $this->stateInfo->touch(),
            stepResults: $stepResults ?? $this->stepResults,
        );
    }

    // STEP RESULT METHODS /////////////////////////////////////

    /**
     * Record a step result (step + continuation outcome bundled).
     */
    public function recordStepResult(StepResult $result): self {
        /** @var ChatStep $step */
        $step = $result->step;

        return $this
            ->withAddedStep($step)
            ->withCurrentStep($step)
            ->with(stepResults: [...$this->stepResults, $result]);
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
     * Get all step results.
     *
     * @return StepResult[]
     */
    public function stepResults(): array {
        return $this->stepResults;
    }

    /**
     * Get the continuation outcome from the last step result.
     */
    public function continuationOutcome(): ?ContinuationOutcome {
        return $this->lastStepResult()?->outcome;
    }

    /**
     * Get the stop reason from the last step result's continuation outcome.
     */
    public function stopReason(): ?StopReason {
        return $this->continuationOutcome()?->stopReason();
    }

    // SERIALIZATION ////////////////////////////////////////

    public function toArray() : array {
        return [
            'steps' => array_map(static fn(ChatStep $s) => $s->toArray(), $this->steps->all()),
            'currentStep' => $this->currentStep?->toArray(),
            'metadata' => $this->metadata->toArray(),
            'usage' => $this->usage->toArray(),
            'messageStore' => $this->store->toArray(),
            'stateInfo' => $this->stateInfo->toArray(),
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
                    static fn(array $stepData) => ChatStep::fromArray($stepData),
                ),
                $data['stepResults'],
            );
        }

        return new self(
            steps: isset($data['steps']) ? new ChatSteps(...array_map(static fn(array $s) => ChatStep::fromArray($s), $data['steps'])) : null,
            currentStep: isset($data['currentStep']) ? ChatStep::fromArray($data['currentStep']) : null,
            variables: isset($data['metadata']) ? new Metadata($data['metadata']) : null,
            usage: isset($data['usage']) ? Usage::fromArray($data['usage']) : null,
            store: isset($data['messageStore']) ? MessageStore::fromArray($data['messageStore']) : null,
            stateInfo: isset($data['stateInfo']) ? StateInfo::fromArray($data['stateInfo']) : null,
            stepResults: $stepResults,
        );
    }
}
