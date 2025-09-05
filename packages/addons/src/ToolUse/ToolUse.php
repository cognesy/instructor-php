<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse;

use Cognesy\Addons\ToolUse\Contracts\CanUseTools;
use Cognesy\Addons\ToolUse\Contracts\ToolUseObserver;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Addons\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\ToolUse\Events\ToolUseStepCompleted;
use Cognesy\Addons\ToolUse\Events\ToolUseStepStarted;
use Cognesy\Addons\ToolUse\Traits\ToolUse\HandlesContinuationCriteria;
use Cognesy\Addons\ToolUse\Traits\ToolUse\HandlesStepProcessors;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Events\Traits\HandlesEvents;
use Cognesy\Messages\Messages;
use Generator;
use Psr\EventDispatcher\EventDispatcherInterface;

class ToolUse {
    use HandlesEvents;
    use HandlesContinuationCriteria;
    use HandlesStepProcessors;

    private CanUseTools $driver;
    private Data\StepProcessors $processors;
    private Data\ContinuationCriteria $continuationCriteria;

    private ToolUseState $state;
    private ?ToolUseObserver $observer = null;

    public function __construct(
        ?ToolUseState $state = null,
        ?CanUseTools $driver = null,
        ?array $processors = null,
        ?array $continuationCriteria = null,
        null|CanHandleEvents|EventDispatcherInterface $events = null,
    ) {
        $this->events = EventBusResolver::using($events);
        $this->state = $state ?? new ToolUseState;
        $this->driver = $driver ?? new ToolCallingDriver;
        $this->processors = new Data\StepProcessors();
        if (!empty($processors)) {
            // accept legacy arrays of processors
            foreach ($processors as $p) { $this->withProcessors($p); }
        } else { $this->withDefaultProcessors(); }
        $this->continuationCriteria = new Data\ContinuationCriteria();
        if (!empty($continuationCriteria)) {
            foreach ($continuationCriteria as $c) { $this->withContinuationCriteria($c); }
        } else { $this->withDefaultContinuationCriteria(); }
        // ensure Tools collection dispatches via the same event bus
        $this->state->tools()->withEventHandler($this->events);
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
        // propagate event handler to Tools
        $tools->withEventHandler($this->events);
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
        if ($this->observer) { $this->observer->onStepStart($this->state); }
        // emit event: step started
        $this->dispatch(new ToolUseStepStarted([
            'step' => $this->state->stepCount() + 1,
            'messages' => $this->state->messages()->count(),
            'tools' => count($this->state->tools()->nameList()),
        ]));
        $step = $this->driver->useTools($this->state);
        $step = $this->processStep($step, $this->state);
        if ($this->observer) { $this->observer->onStepEnd($this->state, $step); }
        // emit event: step completed
        $this->dispatch(new ToolUseStepCompleted([
            'step' => $this->state->stepCount(),
            'hasToolCalls' => $step->hasToolCalls(),
            'errors' => count($step->errors()),
            'usage' => $step->usage()->toArray(),
            'finishReason' => $step->finishReason()?->value,
        ]));
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
