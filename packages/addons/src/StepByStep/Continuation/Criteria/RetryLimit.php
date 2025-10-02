<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation\Criteria;

use Closure;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;

/**
 * Stops after a configurable number of consecutive failed steps.
 *
 * @template TState of object
 * @template TStep of object
 * @implements CanDecideToContinue<TState>
 */
final readonly class RetryLimit implements CanDecideToContinue
{
    /** @var Closure(TState): iterable<TStep> */
    private Closure $stepSequence;
    /** @var Closure(TStep): bool */
    private Closure $stepHasError;

    /**
     * @param Closure(TState): iterable<TStep> $stepSequence Provides steps in chronological order.
     * @param Closure(TStep): bool $stepHasError Indicates whether the step should be treated as a failure.
     */
    public function __construct(
        private int $maxConsecutiveFailures,
        callable $stepSequence,
        callable $stepHasError,
    ) {
        $this->stepSequence = Closure::fromCallable($stepSequence);
        $this->stepHasError = Closure::fromCallable($stepHasError);
    }

    /**
     * @param TState $state
     */
    #[\Override]
    public function canContinue(object $state): bool {
        /** @var TState $state */
        $steps = $this->collectSteps($state);
        if ($steps === []) {
            return true;
        }

        $failedTail = 0;
        for ($i = count($steps) - 1; $i >= 0; $i--) {
            $step = $steps[$i];
            if (($this->stepHasError)($step) === false) {
                break;
            }
            $failedTail++;
            if ($failedTail > $this->maxConsecutiveFailures) {
                break;
            }
        }

        if ($failedTail === 0) {
            return true;
        }

        return $failedTail < $this->maxConsecutiveFailures;
    }

    /**
     * @param TState $state
     * @return array<int, TStep>
     */
    private function collectSteps(object $state): array {
        $steps = [];
        foreach (($this->stepSequence)($state) as $step) {
            $steps[] = $step;
        }
        return $steps;
    }
}
