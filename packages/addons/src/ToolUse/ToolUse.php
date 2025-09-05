<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse;

use Cognesy\Addons\ToolUse\Contracts\CanUseTools;
use Cognesy\Addons\ToolUse\Drivers\ToolCallingDriver;
use Cognesy\Addons\ToolUse\Traits\ToolUse\HandlesContinuationCriteria;
use Cognesy\Addons\ToolUse\Traits\ToolUse\HandlesStepProcessors;
use Cognesy\Messages\Messages;
use Generator;
use Cognesy\Addons\ToolUse\Contracts\ToolUseObserver;

class ToolUse {
    use HandlesContinuationCriteria;
    use HandlesStepProcessors;

    private CanUseTools $driver;
    private \Cognesy\Addons\ToolUse\Collections\StepProcessors $processors;
    private \Cognesy\Addons\ToolUse\Collections\ContinuationCriteria $continuationCriteria;

    private ToolUseState $state;
    private ?ToolUseObserver $observer = null;

    public function __construct(
        ?ToolUseState $state = null,
        ?CanUseTools $driver = null,
        ?array $processors = null,
        ?array $continuationCriteria = null
    ) {
        $this->state = $state ?? new ToolUseState;
        $this->driver = $driver ?? new ToolCallingDriver;
        $this->processors = new \Cognesy\Addons\ToolUse\Collections\StepProcessors();
        if (!empty($processors)) {
            // accept legacy arrays of processors
            foreach ($processors as $p) { $this->withProcessors($p); }
        } else { $this->withDefaultProcessors(); }
        $this->continuationCriteria = new \Cognesy\Addons\ToolUse\Collections\ContinuationCriteria();
        if (!empty($continuationCriteria)) {
            foreach ($continuationCriteria as $c) { $this->withContinuationCriteria($c); }
        } else { $this->withDefaultContinuationCriteria(); }
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

    public function withObserver(ToolUseObserver $observer) : self {
        $this->observer = $observer;
        return $this;
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
        if ($this->observer) {
            $this->observer->onStepStart($this->state);
        }
        $step = $this->driver->useTools($this->state);
        $step = $this->processStep($step, $this->state);
        if ($this->observer) {
            $this->observer->onStepEnd($this->state, $step);
        }
        return $step;
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
