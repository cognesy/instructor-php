<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Continuation\Criteria;

use Closure;
use Cognesy\Agents\Core\Continuation\Contracts\CanEvaluateContinuation;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Data\AgentState;

/**
 * Work driver: Uses a predicate to decide whether to continue based on the latest response content.
 *
 * Acts as a hybrid:
 * - No response yet (null): AllowContinuation (permits bootstrap)
 * - Predicate returns true: RequestContinuation (has work to do)
 * - Predicate returns false: AllowStop (work complete)
 */
final readonly class ResponseContentCheck implements CanEvaluateContinuation
{
    /** @var Closure(AgentState): (object|null) */
    private Closure $responseResolver;
    /** @var Closure(object|null): bool */
    private Closure $predicate;

    /**
     * @param Closure(AgentState): (object|null) $responseResolver
     * @param Closure(object|null): bool $predicate Returns true to signal continuation, false to allow stop
     */
    public function __construct(callable $responseResolver, callable $predicate) {
        $this->responseResolver = Closure::fromCallable($responseResolver);
        $this->predicate = Closure::fromCallable($predicate);
    }

    #[\Override]
    public function evaluate(AgentState $state): ContinuationEvaluation {
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
