<?php declare(strict_types=1);

namespace Cognesy\Addons\Collaboration\Data;

use Cognesy\Addons\Collaboration\Collections\CollaborationSteps;
use Cognesy\Addons\Collaboration\State\HandlesCollaborationSteps;
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

/** @implements HasSteps<CollaborationStep> */
final readonly class CollaborationState implements HasSteps, HasMetadata, HasMessageStore, HasUsage, HasStateInfo
{
    use HandlesCollaborationSteps;
    use HandlesMessageStore;
    use HandlesMetadata;
    use HandlesStateInfo;
    use HandlesUsage;

    /** @var StepResult[] */
    private array $stepResults;

    public function __construct(
        ?CollaborationSteps $steps = null,
        ?CollaborationStep  $currentStep = null,

        Metadata|array|null $variables = null,
        ?Usage              $usage = null,
        ?MessageStore       $store = null,
        ?StateInfo          $stateInfo = null,
        ?array              $stepResults = null,
    ) {
        $this->steps = $steps ?? new CollaborationSteps();
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
        ?CollaborationSteps $steps = null,
        ?CollaborationStep  $currentStep = null,
        ?Metadata           $variables = null,
        ?Usage              $usage = null,
        ?MessageStore       $store = null,
        ?StateInfo          $stateInfo = null,
        ?array              $stepResults = null,
    ): static {
        return new static(
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
        /** @var CollaborationStep $step */
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
            'steps' => array_map(fn(CollaborationStep $s) => $s->toArray(), $this->steps->all()),
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
                    static fn(array $stepData) => CollaborationStep::fromArray($stepData),
                ),
                $data['stepResults'],
            );
        }

        return new self(
            steps: isset($data['steps']) ? new CollaborationSteps(...array_map(fn(array $s) => CollaborationStep::fromArray($s), $data['steps'])) : null,
            currentStep: isset($data['currentStep']) ? CollaborationStep::fromArray($data['currentStep']) : null,
            variables: isset($data['metadata']) ? new Metadata($data['metadata']) : null,
            usage: isset($data['usage']) ? Usage::fromArray($data['usage']) : null,
            store: isset($data['messageStore']) ? MessageStore::fromArray($data['messageStore']) : null,
            stateInfo: isset($data['stateInfo']) ? StateInfo::fromArray($data['stateInfo']) : null,
            stepResults: $stepResults,
        );
    }
}
