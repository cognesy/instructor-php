<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Data;

use Cognesy\Addons\Shared\MessageExchangeState;
use Cognesy\Addons\ToolUse\Data\Collections\ToolUseSteps;
use Cognesy\Addons\ToolUse\Enums\ToolUseStatus;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Metadata;
use DateTimeImmutable;

final readonly class ToolUseState extends MessageExchangeState
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
        ?string $id = null,
        ?DateTimeImmutable $startedAt = null,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        parent::__construct(
            variables: $variables,
            usage: $usage,
            store: $store,
            id: $id,
            startedAt: $startedAt,
            updatedAt: $updatedAt,
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
            id: $data['id'] ?? null,
            startedAt: isset($data['startedAt']) ? new DateTimeImmutable($data['startedAt']) : new DateTimeImmutable(),
            updatedAt: isset($data['updatedAt']) ? new DateTimeImmutable($data['updatedAt']) : new DateTimeImmutable(),
        );
    }

    // MUTATORS ////////////////////////////////////////////////

    public function withCurrentStep(ToolUseStep $step) : self {
        return new self(
            status: $this->status,
            steps: $this->steps,
            currentStep: $step,
            variables: $this->metadata,
            usage: $this->usage,
            store: $this->store,
            id: $this->id,
            startedAt: $this->startedAt,
            updatedAt: $this->updatedAt,
        );
    }

    public function withMessages(Messages $messages) : self {
        return new self(
            status: $this->status,
            steps: $this->steps,
            currentStep: $this->currentStep,
            variables: $this->metadata,
            usage: $this->usage,
            store: $this->store->section(self::DEFAULT_SECTION)->setMessages($messages),
            id: $this->id,
            startedAt: $this->startedAt,
            updatedAt: $this->updatedAt,
        );
    }

    public function withVariable(int|string $name, mixed $value) : self {
        return new self(
            status: $this->status,
            steps: $this->steps,
            currentStep: $this->currentStep,
            variables: $this->metadata->withKeyValue($name, $value),
            usage: $this->usage,
            store: $this->store,
            id: $this->id,
            startedAt: $this->startedAt,
            updatedAt: $this->updatedAt,
        );
    }

    public function withStatus(ToolUseStatus $status) : self {
        return new self(
            status: $status,
            steps: $this->steps,
            currentStep: $this->currentStep,
            variables: $this->metadata,
            usage: $this->usage,
            store: $this->store,
            id: $this->id,
            startedAt: $this->startedAt,
            updatedAt: $this->updatedAt,
        );
    }

    public function withAddedStep(ToolUseStep $step) : self {
        return new self(
            status: $this->status,
            steps: $this->steps->withAddedStep($step),
            currentStep: $step,
            variables: $this->metadata,
            usage: $this->usage,
            store: $this->store,
            id: $this->id,
            startedAt: $this->startedAt,
            updatedAt: $this->updatedAt,
        );
    }

    public function appendMessages(Messages $messages) : self {
        // Get the current messages from the default section and append new ones
        $currentMessages = $this->messages();
        $combinedMessages = $currentMessages->appendMessages($messages);
        
        return new self(
            status: $this->status,
            steps: $this->steps,
            currentStep: $this->currentStep,
            variables: $this->metadata,
            usage: $this->usage,
            store: $this->store->section(self::DEFAULT_SECTION)->setMessages($combinedMessages),
            id: $this->id,
            startedAt: $this->startedAt,
            updatedAt: $this->updatedAt,
        );
    }

    public function withAccumulatedUsage(Usage $usage) : self {
        return new self(
            status: $this->status,
            steps: $this->steps,
            currentStep: $this->currentStep,
            variables: $this->metadata,
            usage: Usage::copy($this->usage)->accumulate($usage),
            store: $this->store,
            id: $this->id,
            startedAt: $this->startedAt,
            updatedAt: $this->updatedAt,
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
