<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Data;

use Cognesy\Addons\StepByStep\State\Contracts\HasMessageStore;
use Cognesy\Addons\StepByStep\State\Contracts\HasMetadata;
use Cognesy\Addons\StepByStep\State\Contracts\HasStateInfo;
use Cognesy\Addons\StepByStep\State\Contracts\HasSteps;
use Cognesy\Addons\StepByStep\State\Contracts\HasUsage;
use Cognesy\Addons\StepByStep\State\StateInfo;
use Cognesy\Addons\StepByStep\State\Traits\HandlesMessageStore;
use Cognesy\Addons\StepByStep\State\Traits\HandlesMetadata;
use Cognesy\Addons\StepByStep\State\Traits\HandlesStateInfo;
use Cognesy\Addons\StepByStep\State\Traits\HandlesToolUseSteps;
use Cognesy\Addons\StepByStep\State\Traits\HandlesUsage;
use Cognesy\Addons\ToolUse\Collections\ToolUseSteps;
use Cognesy\Addons\ToolUse\Enums\ToolUseStatus;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Metadata;

/** @implements HasSteps<ToolUseStep> */
final readonly class ToolUseState implements HasSteps, HasMessageStore, HasMetadata, HasUsage, HasStateInfo
{
    use HandlesMessageStore;
    use HandlesMetadata;
    use HandlesStateInfo;
    use HandlesToolUseSteps;
    use HandlesUsage;

    private ToolUseStatus $status;

    public function __construct(
        ?ToolUseStatus $status = null,
        ?ToolUseSteps $steps = null,
        ?ToolUseStep $currentStep = null,

        Metadata|array|null $variables = null,
        ?Usage $usage = null,
        ?MessageStore $store = null,
        ?StateInfo $stateInfo = null,
    ) {
        $this->status = $status ?? ToolUseStatus::InProgress;
        $this->steps = $steps ?? new ToolUseSteps();
        $this->currentStep = $currentStep ?? null;

        $this->stateInfo = $stateInfo ?? StateInfo::new();
        $this->metadata = match(true) {
            $variables === null => new Metadata(),
            $variables instanceof Metadata => $variables,
            is_array($variables) => new Metadata($variables),
            default => new Metadata(),
        };
        $this->usage = $usage ?? new Usage();
        $this->store = $store ?? new MessageStore();
    }

    // CONSTRUCTORS ////////////////////////////////////////////

    public static function empty() : self {
        return new self();
    }

    // MUTATORS ////////////////////////////////////////////////

    public function with(
        ?ToolUseStatus $status = null,
        ?ToolUseSteps $steps = null,
        ?ToolUseStep $currentStep = null,

        ?Metadata $variables = null,
        ?Usage $usage = null,
        ?MessageStore $store = null,
        ?StateInfo $stateInfo = null,
    ): static {
        return new static(
            status: $status ?? $this->status,
            steps: $steps ?? $this->steps,
            currentStep: $currentStep ?? $this->currentStep,
            variables: $variables ?? $this->metadata,
            usage: $usage ?? $this->usage,
            store: $store ?? $this->store,
            stateInfo: $stateInfo ?? $this->stateInfo->touch(),
        );
    }

    public function withStatus(ToolUseStatus $status) : static {
        return $this->with(status: $status);
    }

    // ACCESSORS ////////////////////////////////////////////////

    public function status() : ToolUseStatus {
        return $this->status;
    }

    // SERIALIZATION ////////////////////////////////////////

    public function toArray() : array {
        return [
            'metadata' => $this->metadata->toArray(),
            'usage' => $this->usage->toArray(),
            'messageStore' => $this->store->toArray(),
            'stateInfo' => $this->stateInfo->toArray(),
            'currentStep' => $this->currentStep?->toArray(),
            'status' => $this->status->value,
            'steps' => array_map(fn(ToolUseStep $step) => $step->toArray(), $this->steps->all()),
        ];
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
}
