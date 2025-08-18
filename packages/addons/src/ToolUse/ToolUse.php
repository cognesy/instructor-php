<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse;

use Cognesy\Addons\ToolUse\Contracts\CanUseTools;
use Cognesy\Addons\ToolUse\Drivers\ToolCallingDriver;
use Cognesy\Addons\ToolUse\Traits\ToolUse\HandlesContinuationCriteria;
use Cognesy\Addons\ToolUse\Traits\ToolUse\HandlesStepProcessors;
use Cognesy\Messages\Messages;
use Generator;

class ToolUse {
    use HandlesContinuationCriteria;
    use HandlesStepProcessors;

    private CanUseTools $driver;
    private array $processors;
    private array $continuationCriteria;

    private ToolUseState $state;

    public function __construct(
        ?ToolUseState $state = null,
        ?CanUseTools $driver = null,
        ?array $processors = null,
        ?array $continuationCriteria = null
    ) {
        $this->state = $state ?? new ToolUseState;
        $this->driver = $driver ?? new ToolCallingDriver;
        $this->processors = $processors ?? [];
        if (empty($this->processors)) {
            $this->withDefaultProcessors();
        }
        $this->continuationCriteria = $continuationCriteria ?? [];
        if (empty($this->continuationCriteria)) {
            $this->withDefaultContinuationCriteria();
        }
    }

    // HANDLE PARAMETRIZATION //////////////////////////////////////

    public function withDriver(CanUseTools $driver) : self {
        $this->driver = $driver;
        return $this;
    }

    public function driver() : CanUseTools {
        return $this->driver;
    }

    public function withState(ToolUseState $state) : self {
        $this->state = $state;
        return $this;
    }

    public function state() : ToolUseState {
        return $this->state;
    }

    public function withTools(array|Tools $tools) : self {
        if (is_array($tools)) {
            $tools = new Tools($tools);
        }
        $this->state->withTools($tools);
        return $this;
    }

    public function withMessages(string|array|Messages $messages) : self {
        $messages = match(true) {
            is_string($messages) => [['role' => 'user', 'content' => $messages]],
            is_array($messages) => $messages,
            is_object($messages) && ($messages instanceof Messages) => $messages->toArray(),
            default => []
        };
        $this->state->withMessages(Messages::fromArray($messages));
        return $this;
    }

    public function messages() : Messages {
        return $this->state->messages();
    }

    // HANDLE TOOL USE /////////////////////////////////////////////

    public function nextStep() : ToolUseStep {
        $step = $this->driver->useTools($this->state);
        return $this->processStep($step, $this->state);
    }

    public function finalStep() : ToolUseStep {
        while ($this->hasNextStep()) {
            $this->nextStep();
        }
        return $this->state->currentStep();
    }

    /** @return Generator<ToolUseStep> */
    public function iterator() : iterable {
        while ($this->hasNextStep()) {
            yield $this->nextStep();
        }
    }
}
