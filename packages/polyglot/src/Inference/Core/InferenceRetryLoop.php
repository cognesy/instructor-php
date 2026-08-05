<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Core;

use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Config\LengthRecovery;
use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\InferenceFailureAction;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;

/**
 * Every "should we go round again, and if so with what request" decision for one execution.
 *
 * ONE INSTANCE PER EXECUTION, built in the session constructor. It owns the two counters
 * that used to be a field and a local variable on InferenceExecutionSession -- the attempt
 * budget and the length-recovery budget.
 *
 * WHAT THIS DOES NOT OWN, AND WHY. The plan for instructor-eexl.10 suggested this class take
 * executeResponseLifecycle()'s while-loop as well. It does not, and the code is the reason:
 * every statement in that loop body reads or replaces $execution, which the session owns and
 * must keep owning -- it is what stream() and response() hand back. Moving the body here
 * would mean threading the execution in and out of each step, and a collaborator that
 * receives and returns the session's central piece of state on every call is the session
 * again under a second name. So the loop keeps its body and asks this class the questions.
 */
final class InferenceRetryLoop
{
    private readonly InferenceRetryPolicy $policy;
    private readonly int $maxAttempts;

    /** Length recoveries already spent. Distinct from the attempt budget on purpose. */
    private int $lengthRetries = 0;

    public function __construct(?InferenceRetryPolicy $policy) {
        $this->policy = $policy ?? new InferenceRetryPolicy;
        $this->maxAttempts = max(1, $this->policy->maxAttempts);
    }

    /**
     * A thrown error is retryable only while the attempt budget holds AND the policy
     * classifies it as transient. Both halves matter: an exhausted budget must not retry a
     * retryable error, and a live budget must not retry a permanent one.
     */
    public function shouldRetryAfterException(\Throwable $error, int $attemptNumber): bool {
        return $attemptNumber < $this->maxAttempts
            && $this->policy->shouldRetryException($error);
    }

    /**
     * Classifies a response the caller cannot use. Consumes one length-recovery credit when
     * it returns RecoverFromLength, so repeated truncation terminates instead of looping.
     */
    public function actionForFailedResponse(InferenceResponse $response): InferenceFailureAction {
        $finishReason = $response->finishReason();

        if ($finishReason === InferenceFinishReason::Length && $this->policy->shouldRecoverFromLength($this->lengthRetries)) {
            $this->lengthRetries++;

            return InferenceFailureAction::RecoverFromLength;
        }

        return match ($finishReason) {
            InferenceFinishReason::ContentFilter => InferenceFailureAction::ContentFilterBlocked,
            default => InferenceFailureAction::Terminal,
        };
    }

    public function awaitRetryDelay(int $attemptNumber): void {
        $delayMs = $this->policy->delayMsForAttempt($attemptNumber);
        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }
    }

    /**
     * The request to send for a length recovery: either a larger token budget, or the
     * truncated answer fed back as context with a prompt to continue.
     */
    public function lengthRecoveryRequest(InferenceRequest $request, InferenceResponse $response): InferenceRequest {
        $builder = (new InferenceRequestBuilder)->withRequest($request);

        if ($this->policy->lengthRecoveryMode === LengthRecovery::IncreaseMaxTokens) {
            $current = $request->options()['max_tokens'] ?? null;
            $next = $current !== null
                ? $current + max(1, $this->policy->maxTokensIncrement)
                : max(1, $this->policy->maxTokensIncrement);

            return $builder->withMaxTokens($next)->create();
        }

        $messages = $request->messages()
            ->asAssistant($response->content())
            ->asUser($this->policy->lengthContinuePrompt);

        return $builder->withMessages($messages)->create();
    }
}
