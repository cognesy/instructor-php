<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Guards;

use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Agents\Core\Data\AgentState;

/**
 * Guard hook that forbids continuation once token usage limit is reached.
 *
 * @example
 * $hook = new TokenUsageLimitHook(
 *     maxTotalTokens: 100000,
 * );
 * $hookStack = $hookStack->with($hook, priority: 100);
 */
final readonly class TokenUsageLimitHook implements Hook
{
    public function __construct(
        private int $maxTotalTokens,
    ) {}

    #[\Override]
    public function appliesTo(): array
    {
        return [HookType::BeforeStep];
    }

    #[\Override]
    public function process(AgentState $state, HookType $event): AgentState
    {
        $usage = $state->usage();
        $totalTokens = $usage->totalTokens ?? 0;
        $exceeded = $totalTokens >= $this->maxTotalTokens;

        $evaluation = $this->createEvaluation($totalTokens, $exceeded);

        return $state->withEvaluation($evaluation);
    }

    private function createEvaluation(int $totalTokens, bool $exceeded): ContinuationEvaluation
    {
        // Guard hooks use ForbidContinuation when limit exceeded, AllowStop otherwise.
        // Using AllowStop (not AllowContinuation) ensures guards don't drive continuation
        // when there's no work to do - they only block when limits are reached.
        $decision = $exceeded
            ? ContinuationDecision::ForbidContinuation
            : ContinuationDecision::AllowStop;

        $reason = $exceeded
            ? sprintf('Token limit reached: %d/%d', $totalTokens, $this->maxTotalTokens)
            : sprintf('Tokens under limit: %d/%d', $totalTokens, $this->maxTotalTokens);

        return new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $decision,
            reason: $reason,
            context: [
                'totalTokens' => $totalTokens,
                'maxTotalTokens' => $this->maxTotalTokens,
            ],
            stopReason: $exceeded ? StopReason::TokenLimitReached : null,
        );
    }
}
