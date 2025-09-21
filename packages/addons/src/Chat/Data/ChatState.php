<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Data;

use Cognesy\Addons\Chat\Collections\ChatSteps;
use Cognesy\Addons\Core\MessageExchangeState;
use Cognesy\Addons\Core\StateContracts\HasSteps;
use Cognesy\Addons\Core\StateInfo;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Metadata;
use DateTimeImmutable;

/** @implements HasSteps<ChatStep> */
final readonly class ChatState extends MessageExchangeState implements HasSteps
{
    private ChatSteps $steps;
    private ?ChatStep $currentStep;
    
    public function __construct(
        ?ChatSteps $steps = null,
        ?ChatStep $currentStep = null,

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
        $this->steps = $steps ?? new ChatSteps();
        $this->currentStep = $currentStep;
    }

    // CONSTRUCTORS /////////////////////////////////////////////

    public static function fromArray(array $data) : self {
        return new self(
            steps: isset($data['steps']) ? new ChatSteps(...array_map(fn(array $s) => ChatStep::fromArray($s), $data['steps'])) : null,
            currentStep: isset($data['currentStep']) ? ChatStep::fromArray($data['currentStep']) : null,
            variables: isset($data['metadata']) ? new Metadata($data['metadata']) : null,
            usage: isset($data['usage']) ? Usage::fromArray($data['usage']) : null,
            store: isset($data['messageStore']) ? MessageStore::fromArray($data['messageStore']) : null,
            stateInfo: isset($data['stateInfo']) ? StateInfo::fromArray($data['stateInfo']) : null,
        );
    }

    // MUTATORS /////////////////////////////////////////////////

    public function withVariable(int|string $name, mixed $value): self {
        return new self(
            steps: $this->steps,
            currentStep: $this->currentStep,
            variables: $this->metadata->withKeyValue($name, $value),
            usage: $this->usage,
            store: $this->store,
            stateInfo: $this->stateInfo,
        );
    }

    /**
     * @param ChatStep $step
     */
    public function withAddedStep(object $step): static {
        assert($step instanceof ChatStep);
        $newSteps = $this->steps->withAddedStep($step);
        return new self(
            steps: $newSteps,
            currentStep: $this->currentStep,
            variables: $this->metadata,
            usage: $this->usage,
            store: $this->store,
            stateInfo: $this->stateInfo,
        );
    }

    public function withCurrentStep(ChatStep $step): self {
        return new self(
            steps: $this->steps,
            currentStep: $step,
            variables: $this->metadata,
            usage: $this->usage,
            store: $this->store,
            stateInfo: $this->stateInfo,
        );
    }

    public function withUsage(Usage $usage): static {
        return new self(
            steps: $this->steps,
            currentStep: $this->currentStep,
            variables: $this->metadata,
            usage: $usage,
            store: $this->store,
            stateInfo: $this->stateInfo,
        );
    }

    public function withAccumulatedUsage(Usage $usage): static {
        $newUsage = $this->usage->clone();
        $newUsage->accumulate($usage);
        return $this->withUsage($newUsage);
    }

    public function withMessages(Messages $messages) : self {
        return new self(
            steps: $this->steps,
            currentStep: $this->currentStep,
            variables: $this->metadata,
            usage: $this->usage,
            store: $this->store->section(self::DEFAULT_SECTION)->setMessages($messages),
            stateInfo: $this->stateInfo,
        );
    }

    public function withSectionMessages(string $section, Messages $messages) : self {
        return new self(
            steps: $this->steps,
            currentStep: $this->currentStep,
            variables: $this->metadata,
            usage: $this->usage,
            store: $this->store->section($section)->setMessages($messages),
            stateInfo: $this->stateInfo,
        );
    }

    public function withMessageStore(MessageStore $store) : self {
        return new self(
            steps: $this->steps,
            currentStep: $this->currentStep,
            variables: $this->metadata,
            usage: $this->usage,
            store: $store,
            stateInfo: $this->stateInfo,
        );
    }

    /**
     * @param ChatStep ...$step
     */
    public function withAddedSteps(object ...$step): static {
        if ($step === []) {
            return $this;
        }
        foreach ($step as $singleStep) {
            assert($singleStep instanceof ChatStep);
        }
        return new self(
            steps: $this->steps->withAddedSteps(...$step),
            currentStep: $this->currentStep,
            variables: $this->metadata,
            usage: $this->usage,
            store: $this->store,
            stateInfo: $this->stateInfo,
        );
    }

    // ACCESSORS ////////////////////////////////////////////////

    public function currentStep(): ?ChatStep {
        return $this->currentStep;
    }

    public function steps(): ChatSteps {
        return $this->steps;
    }

    public function stepCount(): int {
        return $this->steps->count();
    }

    public function stepAt(int $index): ?ChatStep {
        return $this->steps->stepAt($index);
    }

    /** @return iterable<ChatStep> */
    public function eachStep(): iterable {
        return $this->steps;
    }

    // TRANSFORMATIONS / CONVERSIONS ////////////////////////////

    public function toArray() : array {
        return array_merge(
            parent::toArray(),
            [
                'steps' => array_map(fn(ChatStep $s) => $s->toArray(), $this->steps->all()),
                'currentStep' => $this->currentStep?->toArray(),
            ]
        );
    }
}
