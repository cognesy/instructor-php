<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Data;

use Cognesy\Addons\Chat\Collections\ChatSteps;
use Cognesy\Addons\Chat\State\HandlesChatSteps;
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

    public function __construct(
        ?ChatSteps          $steps = null,
        ?ChatStep           $currentStep = null,
        Metadata|array|null $variables = null,
        ?Usage              $usage = null,
        ?MessageStore       $store = null,
        ?StateInfo          $stateInfo = null,
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
    }

    // MUTATORS /////////////////////////////////////////////////

    public function with(
        ?ChatSteps    $steps = null,
        ?ChatStep     $currentStep = null,
        ?Metadata     $variables = null,
        ?Usage        $usage = null,
        ?MessageStore $store = null,
        ?StateInfo    $stateInfo = null,
    ): self {
        return new self(
            steps: $steps ?? $this->steps,
            currentStep: $currentStep ?? $this->currentStep,
            variables: $variables ?? $this->metadata,
            usage: $usage ?? $this->usage,
            store: $store ?? $this->store,
            stateInfo: $stateInfo ?? $this->stateInfo->touch(),
        );
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
        ];
    }

    public static function fromArray(array $data) : self {
        return new self(
            steps: isset($data['steps']) ? new ChatSteps(...array_map(static fn(array $s) => ChatStep::fromArray($s), $data['steps'])) : null,
            currentStep: isset($data['currentStep']) ? ChatStep::fromArray($data['currentStep']) : null,
            variables: isset($data['metadata']) ? new Metadata($data['metadata']) : null,
            usage: isset($data['usage']) ? Usage::fromArray($data['usage']) : null,
            store: isset($data['messageStore']) ? MessageStore::fromArray($data['messageStore']) : null,
            stateInfo: isset($data['stateInfo']) ? StateInfo::fromArray($data['stateInfo']) : null,
        );
    }
}
