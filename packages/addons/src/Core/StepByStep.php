<?php declare(strict_types=1);

namespace Cognesy\Addons\Core;

use Cognesy\Addons\Core\Contracts\CanExecuteIteratively;
use Generator;
use Throwable;

/**
 * Minimal step-by-step process executor for use by Chat/ToolUse
 *
 * @template TState of object
 * @implements CanExecuteIteratively<TState>
 */
abstract readonly class StepByStep implements CanExecuteIteratively
{
    /**
     * @param TState $state
     * @return TState
     */
    public function nextStep(object $state): object {
        if (!$this->hasNextStep($state)) {
            return $this->handleNoNextStep($state);
        }

        try {
            $nextStep = $this->makeNextStep($state);
        } catch (Throwable $error) {
            return $this->handleFailure($error, $state);
        }

        return $this->updateState($nextStep, $state);
    }

    /**
     * @param TState $state
     */
    public function hasNextStep(object $state): bool {
        return $this->canContinue($state);
    }

    /**
     * @param TState $state
     * @return TState
     */
    public function finalStep(object $state): object {
        while ($this->hasNextStep($state)) {
            $state = $this->nextStep($state);
        }

        $finalState = $this->handleNoNextStep($state);
        return match (true) {
            $finalState === $state => $state,
            default => $finalState,
        };
    }

    /**
     * @param TState $state
     * @return Generator<TState>
     */
    public function iterator(object $state): iterable {
        while ($this->hasNextStep($state)) {
            $state = $this->nextStep($state);
            yield $state;
        }

        $finalState = $this->handleNoNextStep($state);
        if ($finalState !== $state) {
            yield $finalState;
        }
    }

    // ABSTRACT ///////////////////////////////////////////

    /**
     * @param TState $state
     */
    abstract protected function canContinue(object $state): bool;

    /**
     * @param TState $state
     * @return TState
     */
    abstract protected function updateState($nextStep, object $state) : object;

    /**
     * @param TState $state
     * @return TState
     */
    abstract protected function handleFailure(Throwable $error, object $state) : object;

    /**
     * @param TState $state
     * @return TState
     */
    abstract protected function makeNextStep(object $state) : object;

    /**
     * @param TState $state
     * @return TState
     */
    abstract protected function handleNoNextStep(object $state) : object;
}