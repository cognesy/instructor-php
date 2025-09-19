<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\Continuation\Criteria;

use Closure;
use Cognesy\Addons\Core\Continuation\CanDecideToContinue;

/**
 * Stops a process once the number of completed steps reaches a configured maximum.
 *
 * @template TState of object
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

    public function canContinue(object $state): bool {
        return ($this->stepCounter)($state) < $this->maxSteps;
    }
}
