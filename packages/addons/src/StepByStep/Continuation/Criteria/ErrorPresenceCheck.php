<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation\Criteria;

use Closure;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;

/**
 * Stops when the current step reports execution errors.
 *
 * @template TState of object
 */
final readonly class ErrorPresenceCheck implements CanDecideToContinue
{
    /** @var Closure(TState): bool */
    private Closure $hasErrorsResolver;

    /**
     * @param Closure(TState): bool $hasErrorsResolver
     */
    public function __construct(callable $hasErrorsResolver) {
        $this->hasErrorsResolver = Closure::fromCallable($hasErrorsResolver);
    }

    public function canContinue(object $state): bool {
        return !($this->hasErrorsResolver)($state);
    }
}
