<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Data;

use Cognesy\Addons\Core\MessageExchangeState;
use Cognesy\Addons\Core\StateContracts\HasSteps;
use Cognesy\Addons\Core\StateInfo;
use Cognesy\Addons\ToolUse\Collections\ToolUseSteps;
use Cognesy\Addons\ToolUse\Enums\ToolUseStatus;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Metadata;
use DateTimeImmutable;

/** @implements HasSteps<ToolUseStep> */
final readonly class ToolUseState extends MessageExchangeState implements HasSteps
{
    private ToolUseStatus $status;
    private ToolUseSteps $steps;
    private ?ToolUseStep $currentStep;

    public function __construct(
        ?ToolUseStatus $status = null,
        ?ToolUseSteps $steps = null,
        ?ToolUseStep $currentStep = null,

        Metadata|array|null $variables = null,
        ?Usage $usage = null,
        ?MessageStore $store = null,
        ?StateInfo $stateInfo = null,
    ) {
        parent::__construct(
            variables: $variables,
            usage: $usage,
            store: $store,
            stateInfo: $stateInfo,
        );

        $this->status = $status ?? ToolUseStatus::InProgress;
        $this->steps = $steps ?? new ToolUseSteps();
        $this->currentStep = $currentStep ?? null;
    }

    // CONSTRUCTORS ////////////////////////////////////////////

    public static function empty() : self {
        return new self();
    }

    public static function fromArray(array $data) : self {
        return new self(
            status: isset($data['status']) ? ToolUseStatus::from($data['status']) : ToolUseStatus::InProgress,
            steps: isset($data['steps']) ? ToolUseSteps::fromArray($data['steps']) : new ToolUseSteps(),
            currentStep: isset($data['currentStep']) ? ToolUseStep::fromArray($data['currentStep']) : null,

            variables: isset($data['metadata']) ? Metadata::fromArray($data['metadata']) : new Metadata(),
            usage: isset($data['usage']) ? Usage::fromArray($data['usage']) : new Usage(),
            store: isset($data['messageStore']) ? MessageStore::fromArray($data['messageStore']) : new MessageStore(),
            stateInfo: isset($data['stateInfo']) ? StateInfo::fromArray($data['stateInfo']) : null,
        );
    }

    // MUTATORS ////////////////////////////////////////////////

    public function withCurrentStep(ToolUseStep $step) : static {
        return new self(
            status: $this->status,
            steps: $this->steps,
            currentStep: $step,
            variables: $this->metadata,
            usage: $this->usage,
            store: $this->store,
            stateInfo: $this->stateInfo,
        );
    }

    public function withMessages(Messages $messages) : static {
        return new self(
            status: $this->status,
            steps: $this->steps,
            currentStep: $this->currentStep,
            variables: $this->metadata,
            usage: $this->usage,
            store: $this->store->section(self::DEFAULT_SECTION)->setMessages($messages),
            stateInfo: $this->stateInfo,
        );
    }

    public function withVariable(int|string $name, mixed $value) : static {
        return new self(
            status: $this->status,
            steps: $this->steps,
            currentStep: $this->currentStep,
            variables: $this->metadata->withKeyValue($name, $value),
            usage: $this->usage,
            store: $this->store,
            stateInfo: $this->stateInfo,
        );
    }

    public function withStatus(ToolUseStatus $status) : static {
        return new self(
            status: $status,
            steps: $this->steps,
            currentStep: $this->currentStep,
            variables: $this->metadata,
            usage: $this->usage,
            store: $this->store,
            stateInfo: $this->stateInfo,
        );
    }

    /**
     * @param ToolUseStep $step
     */
    public function withAddedStep(object $step) : static {
        assert($step instanceof ToolUseStep);
        return new self(
            status: $this->status,
            steps: $this->steps->withAddedStep($step),
            currentStep: $step,
            variables: $this->metadata,
            usage: $this->usage,
            store: $this->store,
            stateInfo: $this->stateInfo,
        );
    }

    public function withUsage(Usage $usage) : static {
        return new self(
            status: $this->status,
            steps: $this->steps,
            currentStep: $this->currentStep,
            variables: $this->metadata,
            usage: $usage,
            store: $this->store,
            stateInfo: $this->stateInfo,
        );
    }

    public function withAccumulatedUsage(Usage $usage) : static {
        $newUsage = $this->usage->clone();
        $newUsage->accumulate($usage);
        return $this->withUsage($newUsage);
    }

    /**
     * @param ToolUseStep ...$step
     */
    public function withAddedSteps(object ...$step): static {
        if ($step === []) {
            return $this;
        }
        foreach ($step as $singleStep) {
            assert($singleStep instanceof ToolUseStep);
        }
        return new self(
            status: $this->status,
            steps: $this->steps->withAddedSteps(...$step),
            currentStep: $this->currentStep,
            variables: $this->metadata,
            usage: $this->usage,
            store: $this->store,
            stateInfo: $this->stateInfo,
        );
    }

    // ACCESSORS ////////////////////////////////////////////////

    public function currentStep() : ?ToolUseStep {
        return $this->currentStep;
    }

    public function status() : ToolUseStatus {
        return $this->status;
    }

    public function steps() : ToolUseSteps {
        return $this->steps;
    }

    public function stepCount() : int {
        return $this->steps->count();
    }

    public function stepAt(int $index): ?ToolUseStep {
        return $this->steps->stepAt($index);
    }

    /** @return iterable<ToolUseStep> */
    public function eachStep(): iterable {
        return $this->steps;
    }

    // TRANSFORMATIONS / CONVERSIONS ////////////////////////////

    public function toArray() : array {
        return array_merge(
            parent::toArray(),
            [
                'currentStep' => $this->currentStep?->toArray(),
                'status' => $this->status->value,
                'steps' => array_map(fn(ToolUseStep $step) => $step->toArray(), $this->steps->all()),
            ]
        );
    }
}
