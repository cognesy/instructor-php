<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

final readonly class EvalConfig
{
    public function __construct(
        private ?CanRunAgentEvalTarget $target = null,
        private ?CanJudgeAgentEval $judge = null,
        private EvalReporters $reporters = new EvalReporters(),
    ) {}

    public static function default(): self {
        return new self();
    }

    public function withTarget(CanRunAgentEvalTarget $target): self {
        return new self($target, $this->judge, $this->reporters);
    }

    public function withJudge(CanJudgeAgentEval $judge): self {
        return new self($this->target, $judge, $this->reporters);
    }

    public function withReporters(CanReportAgentEvals ...$reporters): self {
        return new self($this->target, $this->judge, new EvalReporters(...$reporters));
    }

    public function withReporter(CanReportAgentEvals $reporter): self {
        return new self($this->target, $this->judge, $this->reporters->with($reporter));
    }

    public function target(): ?CanRunAgentEvalTarget {
        return $this->target;
    }

    public function judge(): ?CanJudgeAgentEval {
        return $this->judge;
    }

    public function reporters(): EvalReporters {
        return $this->reporters;
    }
}
