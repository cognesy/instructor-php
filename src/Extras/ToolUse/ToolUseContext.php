<?php

namespace Cognesy\Instructor\Extras\ToolUse;

use Cognesy\Instructor\Features\LLM\Data\Usage;
use Cognesy\Instructor\Utils\Messages\Messages;

class ToolUseContext
{
    private Tools $tools;
    private Messages $messages;
    private array $variables = [];

    /** @var ToolUseStep[] */
    private array $steps = [];
    private ?ToolUseStep $currentStep = null;

    private Usage $usage;

    public function __construct(
        Tools $tools = null,
    ) {
        $this->tools = $tools ?? new Tools();
        $this->messages = new Messages();
        $this->usage = new Usage();
    }

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

    public function messages() : Messages {
        return $this->messages;
    }

    public function withMessages(Messages $messages) {
        $this->messages = $messages;
    }

    public function appendMessages(Messages $messages) {
        $this->messages->appendMessages($messages);
    }

    public function tools() : Tools {
        return $this->tools;
    }

    public function withTools(Tools $tools) {
        $this->tools = $tools;
    }

    public function usage() : Usage {
        return $this->usage;
    }

    public function accumulateUsage(Usage $usage) {
        $this->usage->accumulate($usage);
    }

    public function setVariable(int|string $name, mixed $value) : void {
        $this->variables[$name] = $value;
    }

    public function getVariable(string $name, mixed $default = null) : mixed {
        return $this->variables[$name] ?? $default;
    }
}
