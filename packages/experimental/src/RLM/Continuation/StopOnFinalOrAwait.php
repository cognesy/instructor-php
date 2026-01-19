<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Continuation;

use Cognesy\Addons\StepByStep\Continuation\CanEvaluateContinuation;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\ContinuationEvaluation;

/**
 * Stop when state indicates final or await has been reached.
 *
 * This is a lightweight criterion; the concrete state class should expose
 * a simple boolean for terminal/await conditions.
 */
final class StopOnFinalOrAwait implements CanEvaluateContinuation
{
    #[\Override]
    public function evaluate(object $state): ContinuationEvaluation {
        if (!method_exists($state, 'isTerminal')) {
            return ContinuationEvaluation::fromDecision(
                self::class,
                ContinuationDecision::AllowContinuation,
            );
        }
        /** @var bool $terminal */
        $terminal = (bool) $state->isTerminal();
        if ($terminal) {
            return ContinuationEvaluation::fromDecision(
                self::class,
                ContinuationDecision::AllowStop,
            );
        }
        return ContinuationEvaluation::fromDecision(
            self::class,
            ContinuationDecision::AllowContinuation,
        );
    }
}
