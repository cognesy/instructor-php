<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Data;

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
use Cognesy\Addons\Agent\Collections\AgentSteps;
use Cognesy\Addons\Agent\Enums\AgentStatus;
use Cognesy\Addons\Agent\State\HandlesAgentSteps;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Metadata;

/** @implements HasSteps<AgentStep> */
final readonly class AgentState implements HasSteps, HasMessageStore, HasMetadata, HasUsage, HasStateInfo
{
    use HandlesMessageStore;
    use HandlesMetadata;
    use HandlesStateInfo;
    use HandlesAgentSteps;
    use HandlesUsage;

    private AgentStatus $status;

    public function __construct(
        ?AgentStatus        $status = null,
        ?AgentSteps         $steps = null,
        ?AgentStep          $currentStep = null,

        Metadata|array|null $variables = null,
        ?Usage              $usage = null,
        ?MessageStore       $store = null,
        ?StateInfo          $stateInfo = null,
    ) {
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
        $this->usage = $usage ?? new Usage();
        $this->store = $store ?? new MessageStore();
    }

    // CONSTRUCTORS ////////////////////////////////////////////

    public static function empty() : self {
        return new self();
    }

    // MUTATORS ////////////////////////////////////////////////

    public function with(
        ?AgentStatus  $status = null,
        ?AgentSteps   $steps = null,
        ?AgentStep    $currentStep = null,

        ?Metadata     $variables = null,
        ?Usage        $usage = null,
        ?MessageStore $store = null,
        ?StateInfo    $stateInfo = null,
    ): self {
        return new self(
            status: $status ?? $this->status,
            steps: $steps ?? $this->steps,
            currentStep: $currentStep ?? $this->currentStep,
            variables: $variables ?? $this->metadata,
            usage: $usage ?? $this->usage,
            store: $store ?? $this->store,
            stateInfo: $stateInfo ?? $this->stateInfo->touch(),
        );
    }

    public function withStatus(AgentStatus $status) : self {
        return $this->with(status: $status);
    }

    // ACCESSORS ////////////////////////////////////////////////

    public function status() : AgentStatus {
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
            'steps' => array_map(static fn(AgentStep $step) => $step->toArray(), $this->steps->all()),
        ];
    }

    public static function fromArray(array $data) : self {
        return new self(
            status: isset($data['status']) ? AgentStatus::from($data['status']) : AgentStatus::InProgress,
            steps: isset($data['steps']) ? AgentSteps::fromArray($data['steps']) : new AgentSteps(),
            currentStep: isset($data['currentStep']) ? AgentStep::fromArray($data['currentStep']) : null,

            variables: isset($data['metadata']) ? Metadata::fromArray($data['metadata']) : new Metadata(),
            usage: isset($data['usage']) ? Usage::fromArray($data['usage']) : new Usage(),
            store: isset($data['messageStore']) ? MessageStore::fromArray($data['messageStore']) : new MessageStore(),
            stateInfo: isset($data['stateInfo']) ? StateInfo::fromArray($data['stateInfo']) : null,
        );
    }
}
