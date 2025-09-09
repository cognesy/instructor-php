<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Data;

use Cognesy\Addons\ToolUse\Data\Collections\ToolUseSteps;
use Cognesy\Addons\ToolUse\Enums\ToolUseStatus;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\Usage;
use DateTimeImmutable;

final readonly class ToolUseState
{
    private ToolUseStatus $status;
    private Messages $messages;
    private array $variables;

    private ToolUseSteps $steps;
    private ?ToolUseStep $currentStep;

    private Usage $usage;
    private DateTimeImmutable $startedAt;
    private ToolUseOptions $options;

    public function __construct(
        ?ToolUseStatus $status = null,
        ?Messages $messages = null,
        ?array $variables = null,
        ?ToolUseSteps $steps = null,
        ?ToolUseStep $currentStep = null,
        ?Usage $usage = null,
        ?DateTimeImmutable $startedAt = null,
        ?ToolUseOptions $options = null,
    ) {
        $this->status = $status ?? ToolUseStatus::InProgress;
        $this->messages = $messages ?? new Messages();
        $this->variables = $variables ?? [];
        $this->steps = $steps ?? new ToolUseSteps();
        $this->currentStep = $currentStep ?? null;
        $this->usage = $usage ?? new Usage();
        $this->startedAt = $startedAt ?? new DateTimeImmutable();
        $this->options = $options ?? new ToolUseOptions();
    }

    // HANDLE MUTATIONS ////////////////////////////////////////////

    public function withCurrentStep(ToolUseStep $step) : self {
        return new self(
            status: $this->status,
            messages: $this->messages,
            variables: $this->variables,
            steps: $this->steps,
            currentStep: $step,
            usage: $this->usage,
            startedAt: $this->startedAt,
            options: $this->options,
        );
    }

    public function withMessages(Messages $messages) : self {
        return new self(
            status: $this->status,
            messages: $messages,
            variables: $this->variables,
            steps: $this->steps,
            currentStep: $this->currentStep,
            usage: $this->usage,
            startedAt: $this->startedAt,
            options: $this->options,
        );
    }

    // withTools removed - tools now managed by ToolUse class

    public function withVariable(int|string $name, mixed $value) : self {
        $newVariables = $this->variables;
        $newVariables[$name] = $value;
        return new self(
            status: $this->status,
            messages: $this->messages,
            variables: $newVariables,
            steps: $this->steps,
            currentStep: $this->currentStep,
            usage: $this->usage,
            startedAt: $this->startedAt,
            options: $this->options,
        );
    }

    public function withStatus(ToolUseStatus $status) : self {
        return new self(
            status: $status,
            messages: $this->messages,
            variables: $this->variables,
            steps: $this->steps,
            currentStep: $this->currentStep,
            usage: $this->usage,
            startedAt: $this->startedAt,
            options: $this->options,
        );
    }

    public function withOptions(ToolUseOptions $options) : self {
        return new self(
            status: $this->status,
            messages: $this->messages,
            variables: $this->variables,
            steps: $this->steps,
            currentStep: $this->currentStep,
            usage: $this->usage,
            startedAt: $this->startedAt,
            options: $options,
        );
    }

    public function withAddedStep(ToolUseStep $step) : self {
        return new self(
            status: $this->status,
            messages: $this->messages,
            variables: $this->variables,
            steps: $this->steps->withAddedStep($step),
            currentStep: $step,
            usage: $this->usage,
            startedAt: $this->startedAt,
            options: $this->options,
        );
    }

    public function appendMessages(Messages $messages) : self {
        return new self(
            status: $this->status,
            messages: $this->messages->appendMessages($messages),
            variables: $this->variables,
            steps: $this->steps,
            currentStep: $this->currentStep,
            usage: $this->usage,
            startedAt: $this->startedAt,
            options: $this->options,
        );
    }

    public function accumulateUsage(Usage $usage) : self {
        return new self(
            status: $this->status,
            messages: $this->messages,
            variables: $this->variables,
            steps: $this->steps,
            currentStep: $this->currentStep,
            usage: Usage::copy($this->usage)->accumulate($usage),
            startedAt: $this->startedAt,
            options: $this->options,
        );
    }

    // HANDLE ACCESS ////////////////////////////////////////////////

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
        return $this->messages;
    }

    // Tools moved to ToolUse class

    public function usage() : Usage {
        return $this->usage;
    }

    public function startedAt() : DateTimeImmutable {
        return $this->startedAt;
    }

    public function options() : ToolUseOptions {
        return $this->options;
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
