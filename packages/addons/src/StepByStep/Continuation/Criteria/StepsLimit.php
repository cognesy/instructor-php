<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation\Criteria;

use Closure;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;

/**
 * Stops a process once the number of completed steps reaches a configured maximum.
 *
 * @template TState of object
 * @implements CanDecideToContinue<TState>
 */
final readonly class StepsLimit implements CanDecideToContinue
{
    /** @var Closure(TState): int */
    private Closure $stepCounter;

    /**
     * @param Closure(TState): int $stepCounter Extracts the number of completed steps for the state.
     */
    public function __construct(
        private int $maxSteps,
        callable $stepCounter,
    ) {
        $this->stepCounter = Closure::fromCallable($stepCounter);
    }

    /**
     * @param TState $state
     */
    #[\Override]
    public function canContinue(object $state): bool {
        /** @var TState $state */
        return ($this->stepCounter)($state) < $this->maxSteps;
    }
}
