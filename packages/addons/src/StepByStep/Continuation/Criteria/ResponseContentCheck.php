<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation\Criteria;

use Closure;
use Cognesy\Addons\StepByStep\Continuation\CanEvaluateContinuation;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\ContinuationEvaluation;

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
 * @implements CanEvaluateContinuation<TState>
 */
final readonly class ResponseContentCheck implements CanEvaluateContinuation
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
    public function evaluate(object $state): ContinuationEvaluation {
        /** @var TState $state */
        $response = ($this->responseResolver)($state);
        if ($response === null) {
            return new ContinuationEvaluation(
                criterionClass: self::class,
                decision: ContinuationDecision::AllowContinuation,
                reason: 'No response yet, allowing bootstrap',
                context: ['hasResponse' => false],
            );
        }

        $shouldContinue = ($this->predicate)($response);
        $decision = $shouldContinue
            ? ContinuationDecision::RequestContinuation
            : ContinuationDecision::AllowStop;

        $reason = $shouldContinue
            ? 'Response content check requests continuation'
            : 'Response content check allows stop';

        return new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $decision,
            reason: $reason,
            context: ['hasResponse' => true, 'shouldContinue' => $shouldContinue],
        );
    }
}
