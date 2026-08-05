<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Closure;
use Override;

final readonly class FakeAgentJudge implements CanJudgeAgentEval
{
    /** @param Closure(JudgeRequest): JudgeScore $judge */
    private function __construct(private Closure $judge) {}

    public static function fromScore(float $score, string $reason = 'deterministic score'): self {
        return new self(static fn (JudgeRequest $request): JudgeScore => new JudgeScore($score, $reason));
    }

    /** @param Closure(JudgeRequest): JudgeScore $judge */
    public static function fromClosure(Closure $judge): self {
        return new self($judge);
    }

    #[Override]
    public function judge(JudgeRequest $request): JudgeScore {
        return ($this->judge)($request);
    }
}
