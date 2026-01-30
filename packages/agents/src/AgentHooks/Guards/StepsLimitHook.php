<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Guards;

use Closure;
use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Agents\Core\Data\AgentState;

/**
 * Guard hook that forbids continuation once step limit is reached.
 *
 * @example
 * $hook = new StepsLimitHook(
 *     maxSteps: 10,
 *     stepCounter: fn(AgentState $state) => $state->stepCount(),
 * );
 * $hookStack = $hookStack->with($hook, priority: 100);
 */
final readonly class StepsLimitHook implements Hook
{
    /** @var Closure(AgentState): int */
    private Closure $stepCounter;

    /**
     * @param int $maxSteps Maximum allowed steps
     * @param callable(AgentState): int $stepCounter Extracts completed step count from state
     */
    public function __construct(
        private int $maxSteps,
        callable $stepCounter,
    ) {
        $this->stepCounter = Closure::fromCallable($stepCounter);
    }

    #[\Override]
    public function appliesTo(): array
    {
        return [HookType::BeforeStep];
    }

    #[\Override]
    public function process(AgentState $state, HookType $event): AgentState
    {
        $currentSteps = ($this->stepCounter)($state);
        $exceeded = $currentSteps >= $this->maxSteps;

        $evaluation = $this->createEvaluation($currentSteps, $exceeded);

        return $state->withEvaluation($evaluation);
    }

    private function createEvaluation(int $currentSteps, bool $exceeded): ContinuationEvaluation
    {
        // Guard hooks use ForbidContinuation when limit exceeded, AllowStop otherwise.
        // Using AllowStop (not AllowContinuation) ensures guards don't drive continuation
        // when there's no work to do - they only block when limits are reached.
        $decision = $exceeded
            ? ContinuationDecision::ForbidContinuation
            : ContinuationDecision::AllowStop;

        $reason = $exceeded
            ? sprintf('Step limit reached: %d/%d', $currentSteps, $this->maxSteps)
            : sprintf('Steps under limit: %d/%d', $currentSteps, $this->maxSteps);

        return new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $decision,
            reason: $reason,
            context: [
                'currentSteps' => $currentSteps,
                'maxSteps' => $this->maxSteps,
            ],
            stopReason: $exceeded ? StopReason::StepsLimitReached : null,
        );
    }
}
