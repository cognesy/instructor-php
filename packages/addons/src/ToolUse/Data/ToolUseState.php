<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Data;

use Cognesy\Addons\ToolUse\Data\Collections\ToolUseSteps;
use Cognesy\Addons\ToolUse\Enums\ToolUseStatus;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Data\Usage;
use DateTimeImmutable;

final readonly class ToolUseState
{
    const DEFAULT_SECTION = 'messages';

    private ToolUseStatus $status;
    private MessageStore $store;
    private array $variables;

    private ToolUseSteps $steps;
    private ?ToolUseStep $currentStep;

    private Usage $usage;
    private DateTimeImmutable $startedAt;

    public function __construct(
        ?ToolUseStatus $status = null,
        ?MessageStore $store = null,
        ?array $variables = null,
        ?ToolUseSteps $steps = null,
        ?ToolUseStep $currentStep = null,
        ?Usage $usage = null,
        ?DateTimeImmutable $startedAt = null,
    ) {
        $this->status = $status ?? ToolUseStatus::InProgress;
        $this->store = $store ?? new MessageStore();
        $this->variables = $variables ?? [];
        $this->steps = $steps ?? new ToolUseSteps();
        $this->currentStep = $currentStep ?? null;
        $this->usage = $usage ?? new Usage();
        $this->startedAt = $startedAt ?? new DateTimeImmutable();
    }

    // MUTATORS ////////////////////////////////////////////////

    public function withCurrentStep(ToolUseStep $step) : self {
        return new self(
            status: $this->status,
            store: $this->store,
            variables: $this->variables,
            steps: $this->steps,
            currentStep: $step,
            usage: $this->usage,
            startedAt: $this->startedAt,
        );
    }

    public function withMessages(Messages $messages) : self {
        return new self(
            status: $this->status,
            store: $this->store->section(self::DEFAULT_SECTION)->setMessages($messages),
            variables: $this->variables,
            steps: $this->steps,
            currentStep: $this->currentStep,
            usage: $this->usage,
            startedAt: $this->startedAt,
        );
    }

    public function withVariable(int|string $name, mixed $value) : self {
        $newVariables = $this->variables;
        $newVariables[$name] = $value;
        return new self(
            status: $this->status,
            store: $this->store,
            variables: $newVariables,
            steps: $this->steps,
            currentStep: $this->currentStep,
            usage: $this->usage,
            startedAt: $this->startedAt,
        );
    }

    public function withStatus(ToolUseStatus $status) : self {
        return new self(
            status: $status,
            store: $this->store,
            variables: $this->variables,
            steps: $this->steps,
            currentStep: $this->currentStep,
            usage: $this->usage,
            startedAt: $this->startedAt,
        );
    }

    public function withAddedStep(ToolUseStep $step) : self {
        return new self(
            status: $this->status,
            store: $this->store,
            variables: $this->variables,
            steps: $this->steps->withAddedStep($step),
            currentStep: $step,
            usage: $this->usage,
            startedAt: $this->startedAt,
        );
    }

    public function appendMessages(Messages $messages) : self {
        // Get the current messages from the default section and append new ones
        $currentMessages = $this->messages();
        $combinedMessages = $currentMessages->appendMessages($messages);
        
        return new self(
            status: $this->status,
            store: $this->store->section(self::DEFAULT_SECTION)->setMessages($combinedMessages),
            variables: $this->variables,
            steps: $this->steps,
            currentStep: $this->currentStep,
            usage: $this->usage,
            startedAt: $this->startedAt,
        );
    }

    public function accumulateUsage(Usage $usage) : self {
        return new self(
            status: $this->status,
            store: $this->store,
            variables: $this->variables,
            steps: $this->steps,
            currentStep: $this->currentStep,
            usage: Usage::copy($this->usage)->accumulate($usage),
            startedAt: $this->startedAt,
        );
    }

    // ACCESSORS ////////////////////////////////////////////////

    public function currentStep() : ?ToolUseStep {
        return $this->currentStep;
    }

    public function steps() : ToolUseSteps {
        return $this->steps;
    }

    public function stepCount() : int {
        return $this->steps->count();
    }

    public function messages() : Messages {
        return $this->store->toMessages();
    }

    public function usage() : Usage {
        return $this->usage;
    }

    public function startedAt() : DateTimeImmutable {
        return $this->startedAt;
    }

    public function status() : ToolUseStatus {
        return $this->status;
    }

    public function variables() : array {
        return $this->variables;
    }

    public function variable(string $name, mixed $default = null) : mixed {
        return $this->variables[$name] ?? $default;
    }
}
