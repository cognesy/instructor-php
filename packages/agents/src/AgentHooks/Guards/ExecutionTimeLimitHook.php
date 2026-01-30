<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Guards;

use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\AgentHooks\Guards\Contracts\CanStartExecution;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Agents\Core\Data\AgentState;
use DateTimeImmutable;

/**
 * Guard hook that forbids continuation once execution time limit is reached.
 *
 * @example
 * $hook = new ExecutionTimeLimitHook(
 *     maxSeconds: 120, // 2 minutes
 * );
 * $hookStack = $hookStack->with($hook, priority: 100);
 */
final class ExecutionTimeLimitHook implements Hook, CanStartExecution
{
    private ?DateTimeImmutable $executionStartedAt = null;

    public function __construct(
        private readonly float $maxSeconds,
    ) {}

    public function executionStarted(DateTimeImmutable $startedAt): void
    {
        $this->executionStartedAt = $startedAt;
    }

    #[\Override]
    public function appliesTo(): array
    {
        return [HookType::BeforeStep];
    }

    #[\Override]
    public function process(AgentState $state, HookType $event): AgentState
    {
        // If execution hasn't started yet, allow continuation
        if ($this->executionStartedAt === null) {
            return $state->withEvaluation($this->createEvaluation(0, false));
        }

        $now = new DateTimeImmutable();
        $elapsedSeconds = (float) $now->format('U.u') - (float) $this->executionStartedAt->format('U.u');
        $exceeded = $elapsedSeconds >= $this->maxSeconds;

        $evaluation = $this->createEvaluation($elapsedSeconds, $exceeded);

        return $state->withEvaluation($evaluation);
    }

    private function createEvaluation(float $elapsedSeconds, bool $exceeded): ContinuationEvaluation
    {
        // Guard hooks use ForbidContinuation when limit exceeded, AllowStop otherwise.
        // Using AllowStop (not AllowContinuation) ensures guards don't drive continuation
        // when there's no work to do - they only block when limits are reached.
        $decision = $exceeded
            ? ContinuationDecision::ForbidContinuation
            : ContinuationDecision::AllowStop;

        $reason = $exceeded
            ? sprintf('Execution time limit reached: %.2fs/%.2fs', $elapsedSeconds, $this->maxSeconds)
            : sprintf('Execution time under limit: %.2fs/%.2fs', $elapsedSeconds, $this->maxSeconds);

        return new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $decision,
            reason: $reason,
            context: [
                'elapsedSeconds' => $elapsedSeconds,
                'maxSeconds' => $this->maxSeconds,
            ],
            stopReason: $exceeded ? StopReason::TimeLimitReached : null,
        );
    }
}
