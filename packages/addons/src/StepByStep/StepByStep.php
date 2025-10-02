<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep;

use Cognesy\Addons\StepByStep\Contracts\CanExecuteIteratively;
use Cognesy\Addons\StepByStep\StateProcessing\CanApplyProcessors;
use Throwable;

/**
 * Minimal step-by-step process executor for use by Chat/ToolUse
 *
 * @template TState of object
 * @template TStep of object
 * @implements CanExecuteIteratively<TState>
 */
abstract class StepByStep implements CanExecuteIteratively
{
    /** @var CanApplyProcessors<TState>|null */
    protected ?CanApplyProcessors $processors;

    /**
     * @param CanApplyProcessors<TState>|null $processors
     */
    public function __construct(?CanApplyProcessors $processors = null) {
        $this->processors = $processors;
    }

    #[\Override]
    public function nextStep(object $state): object {
        return match(true) {
            !$this->hasNextStep($state) => $this->onNoNextStep($state),
            ($this->hasProcessors()) => $this->performThroughProcessors($state),
            default => $this->performStep($state),
        };
    }

    /**
     * @param TState $state
     */
    #[\Override]
    public function hasNextStep(object $state): bool {
        return $this->canContinue($state);
    }

    /**
     * @param TState $state
     * @return TState
     */
    #[\Override]
    public function finalStep(object $state): object {
        while ($this->hasNextStep($state)) {
            $state = $this->nextStep($state);
        }
        return $this->onNoNextStep($state);
    }

    /**
     * @param TState $state
     * @return iterable<TState>
     */
    #[\Override]
    public function iterator(object $state): iterable {
        while ($this->hasNextStep($state)) {
            $state = $this->nextStep($state);
            yield $state;
        }

        $finalState = $this->onNoNextStep($state);
        if ($this->isStateChanged($state, $finalState)) {
            yield $finalState;
        }
    }

    // INTERNAL ////////////////////////////////////////////

    protected function hasProcessors(): bool {
        return $this->processors !== null;
    }

    /**
     * Determine if the state has changed.
     *
     * Default implementation assumes state objects are immutable. Override it if not the case.
     * @param TState $priorState
     * @param TState $newState
     */
    protected function isStateChanged(object $priorState, object $newState): bool {
        return $priorState !== $newState;
    }

    /**
     * @param TState $state
     * @return TState
     */
    protected function performThroughProcessors(object $state): object {
        try {
            return $this->processors->apply(
                $state,
                function(object $state): object {
                    /** @var TState $state */
                    return $this->performStep($state);
                }
            );
        } catch (Throwable $error) {
            return $this->onFailure($error, $state);
        }
    }

    /**
     * @param TState $state
     * @return TState
     */
    protected function performStep(object $state): object {
        try {
            $nextStep = $this->makeNextStep($state);
            $nextState = $this->applyStep(state: $state, nextStep: $nextStep);
            return $this->onStepCompleted($nextState);
        } catch (Throwable $error) {
            return $this->onFailure($error, $state);
        }
    }

    // ABSTRACT ///////////////////////////////////////////

    /**
     * @param TState $state
     */
    abstract protected function canContinue(object $state): bool;

    /**
     * @param TState $state
     * @return TStep
     */
    abstract protected function makeNextStep(object $state) : object;

    /**
     * @param TState $state
     * @param TStep $nextStep
     * @return TState
     */
    abstract protected function applyStep(object $state, object $nextStep): object;

    /**
     * @param TState $state
     * @return TState
     */
    abstract protected function onNoNextStep(object $state) : object;

    /**
     * @param TState $state
     * @return TState
     */
    abstract protected function onStepCompleted(object $state): object;

    /**
     * @param TState $state
     * @return TState
     */
    abstract protected function onFailure(Throwable $error, object $state) : object;
}
