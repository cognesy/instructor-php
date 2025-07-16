<?php

namespace Cognesy\Addons\ToolUse;

use Cognesy\Addons\ToolUse\Enums\ToolUseStatus;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Messages\Messages;

class ToolUseContext
{
    private ToolUseStatus $status = ToolUseStatus::InProgress;
    private Tools $tools;
    private Messages $messages;
    private array $variables = [];

    /** @var ToolUseStep[] */
    private array $steps = [];
    private ?ToolUseStep $currentStep = null;

    private Usage $usage;

    public function __construct(
        ?Tools $tools = null,
    ) {
        $this->tools = $tools ?? new Tools();
        $this->messages = new Messages();
        $this->usage = new Usage();
    }

    // HANDLE STEPS ////////////////////////////////////////////////

    public function currentStep() : ?ToolUseStep {
        return $this->currentStep;
    }

    /** @var ToolUseStep[] */
    public function steps() : array {
        return $this->steps;
    }

    public function stepCount() : int {
        return count($this->steps);
    }

    public function addStep(ToolUseStep $step) {
        $this->steps[] = $step;
    }

    public function setCurrentStep(ToolUseStep $step) {
        $this->currentStep = $step;
    }

    // HANDLE MESSAGES /////////////////////////////////////////////

    public function messages() : Messages {
        return $this->messages;
    }

    public function withMessages(Messages $messages) {
        $this->messages = $messages;
    }

    public function appendMessages(Messages $messages) {
        $this->messages->appendMessages($messages);
    }

    // HANDLE TOOLS ////////////////////////////////////////////////

    public function tools() : Tools {
        return $this->tools;
    }

    public function withTools(Tools $tools) {
        $this->tools = $tools;
    }

    // HANDLE USAGE ////////////////////////////////////////////////

    public function usage() : Usage {
        return $this->usage;
    }

    public function accumulateUsage(Usage $usage) {
        $this->usage->accumulate($usage);
    }

    // HANDLE VARIABLES ////////////////////////////////////////////

    public function withVariable(int|string $name, mixed $value) : void {
        $this->variables[$name] = $value;
    }

    public function variable(string $name, mixed $default = null) : mixed {
        return $this->variables[$name] ?? $default;
    }

    public function variables() : array {
        return $this->variables;
    }

    // HANDLE STATUS ///////////////////////////////////////////////

    public function status() : ToolUseStatus {
        return $this->status;
    }

    public function withStatus(ToolUseStatus $status) {
        $this->status = $status;
    }
}
