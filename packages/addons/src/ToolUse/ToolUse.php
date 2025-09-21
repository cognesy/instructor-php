<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse;

use Cognesy\Addons\Core\Continuation\CanDecideToContinue;
use Cognesy\Addons\Core\Continuation\ContinuationCriteria;
use Cognesy\Addons\Core\Contracts\CanApplyProcessors;
use Cognesy\Addons\Core\Contracts\CanProcessAnyState;
use Cognesy\Addons\Core\StateProcessors;
use Cognesy\Addons\ToolUse\Contracts\CanUseTools;
use Cognesy\Addons\ToolUse\Contracts\ToolInterface;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Events\ToolUseFinished;
use Cognesy\Addons\ToolUse\Events\ToolUseStepCompleted;
use Cognesy\Addons\ToolUse\Events\ToolUseStepStarted;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Generator;

final readonly class ToolUse {
    private Tools $tools;
    private ToolExecutor $toolExecutor;
    private CanUseTools $driver;
    private CanApplyProcessors $processors;
    private ContinuationCriteria $continuationCriteria;
    private CanHandleEvents $events;

    public function __construct(
        Tools $tools,
        CanApplyProcessors $processors,
        ContinuationCriteria $continuationCriteria,
        CanUseTools $driver,
        ?CanHandleEvents $events,
    ) {
        $this->processors = $processors;
        $this->continuationCriteria = $continuationCriteria;
        $this->driver = $driver;
        $this->events = EventBusResolver::using($events);
        $this->tools = $tools;
        $this->toolExecutor = (new ToolExecutor($tools))->withEventHandler($this->events);
    }

    // HANDLE PARAMETRIZATION //////////////////////////////////////

    public function driver() : CanUseTools {
        return $this->driver;
    }

    // HANDLE TOOL USE /////////////////////////////////////////////

    public function nextStep(ToolUseState $state): ToolUseState {
        if (!$this->hasNextStep($state)) {
            $this->emitToolUseFinished($state);
            return $state;
        }
        
        $this->emitToolUseStepStarted($state);
        $step = $this->driver->useTools($state, $this->tools, $this->toolExecutor);
        $newState = $state->withAddedStep($step)->withCurrentStep($step);
        $newState = $this->processors->apply($newState);
        $this->emitToolUseStepCompleted($newState);
        return $newState;
    }

    public function hasNextStep(ToolUseState $state): bool {
        return $this->canContinue($state);
    }

    public function finalStep(ToolUseState $state): ToolUseState {
        while ($this->hasNextStep($state)) {
            $state = $this->nextStep($state);
        }
        return $state;
    }

    /** @return Generator<ToolUseState> */
    public function iterator(ToolUseState $state): iterable {
        while ($this->hasNextStep($state)) {
            $state = $this->nextStep($state);
            yield $state;
        }
    }

    public function tools() : Tools {
        return $this->tools;
    }

    public function toolExecutor(): ToolExecutor
    {
        return $this->toolExecutor;
    }

    // MUTATORS /////////////////////////////////////////////

    public function withProcessors(CanProcessAnyState ...$processors): self {
        return new self(
            tools: $this->tools,
            processors: new StateProcessors(...$processors),
            continuationCriteria: $this->continuationCriteria,
            driver: $this->driver,
            events: $this->events,
        );
    }

    public function withDriver(CanUseTools $driver) : self {
        return new self(
            tools: $this->tools,
            processors: $this->processors,
            continuationCriteria: $this->continuationCriteria,
            driver: $driver,
            events: $this->events,
        );
    }

    public function withContinuationCriteria(CanDecideToContinue ...$continuationCriteria) : self {
        return new self(
            tools: $this->tools,
            processors: $this->processors,
            continuationCriteria: new ContinuationCriteria(...$continuationCriteria),
            driver: $this->driver,
            events: $this->events,
        );
    }

    public function withToolExecutor(ToolExecutor $executor): self
    {
        $executorWithEvents = $executor->withEventHandler($this->events);
        return new self(
            tools: $executorWithEvents->tools(),
            processors: $this->processors,
            continuationCriteria: $this->continuationCriteria,
            driver: $this->driver,
            events: $this->events,
        );
    }

    public function withTools(array|ToolInterface|Tools $tools) : self {
        $tools = match(true) {
            is_array($tools) => new Tools($tools),
            $tools instanceof ToolInterface => new Tools([$tools]),
            $tools instanceof Tools => $tools,
            default => new Tools(),
        };

        return new self(
            tools: $tools,
            processors: $this->processors,
            continuationCriteria: $this->continuationCriteria,
            driver: $this->driver,
            events: $this->events,
        );
    }

    // INTERNAL /////////////////////////////////////////////

    protected function canContinue(ToolUseState $state): bool {
        return $this->continuationCriteria->canContinue($state);
    }

    // EVENTS ////////////////////////////////////////////

    private function emitToolUseFinished(ToolUseState $state) : void {
        $this->events->dispatch(new ToolUseFinished([
            'status' => $state->status()->value,
            'steps' => $state->stepCount(),
            'usage' => $state->usage()->toArray(),
            'errors' => $state->currentStep()?->errorsAsString(),
        ]));
    }

    private function emitToolUseStepStarted(ToolUseState $state) : void {
        $this->events->dispatch(new ToolUseStepStarted([
            'step' => $state->stepCount() + 1,
            'messages' => $state->messages()->count(),
            'tools' => count($this->tools->nameList()),
        ]));
    }

    private function emitToolUseStepCompleted(ToolUseState $state) : void {
        $this->events->dispatch(new ToolUseStepCompleted([
            'step' => $state->stepCount(),
            'hasToolCalls' => $state->currentStep()?->hasToolCalls() ?? false,
            'errors' => count($state->currentStep()?->errors() ?? []),
            'usage' => $state->currentStep()?->usage()->toArray() ?? [],
            'finishReason' => $state->currentStep()?->finishReason()?->value ?? null,
        ]));
    }
}
