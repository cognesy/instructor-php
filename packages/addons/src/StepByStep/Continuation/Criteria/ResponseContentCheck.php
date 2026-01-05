<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation\Criteria;

use Closure;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;

/**
 * Work driver: Uses a predicate to decide whether to continue based on the latest response content.
 *
 * Acts as a hybrid:
 * - No response yet (null): AllowContinuation (permits bootstrap)
 * - Predicate returns true: RequestContinuation (has work to do)
 * - Predicate returns false: AllowStop (work complete)
 *
 * @template TState of object
 * @template TResponse of object|null
 * @implements CanDecideToContinue<TState>
 */
final readonly class ResponseContentCheck implements CanDecideToContinue
{
    /** @var Closure(TState): TResponse */
    private Closure $responseResolver;
    /** @var Closure(TResponse): bool */
    private Closure $predicate;

    /**
     * @param Closure(TState): TResponse $responseResolver
     * @param Closure(TResponse): bool $predicate Returns true to signal continuation, false to allow stop
     */
    public function __construct(callable $responseResolver, callable $predicate) {
        $this->responseResolver = Closure::fromCallable($responseResolver);
        $this->predicate = Closure::fromCallable($predicate);
    }

    /**
     * @param TState $state
     */
    #[\Override]
    public function decide(object $state): ContinuationDecision {
        /** @var TState $state */
        $response = ($this->responseResolver)($state);
        if ($response === null) {
            // No response yet - permit bootstrap (act like a guard)
            return ContinuationDecision::AllowContinuation;
        }

        $shouldContinue = ($this->predicate)($response);

        return $shouldContinue
            ? ContinuationDecision::RequestContinuation
            : ContinuationDecision::AllowStop;
    }
}
