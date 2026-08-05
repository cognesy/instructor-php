<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Throwable;

final class JudgeExpectation
{
    private AssertionHandle $handle;

    public function __construct(
        private readonly ?CanJudgeAgentEval $judge,
        private JudgeRequest $request,
        AssertionCollector $collector,
    ) {
        $missing = $judge === null;
        $this->handle = $collector->record(new AssertionResult(
            name: 'judge:' . $request->criterion,
            score: 0.0,
            severity: $missing ? AssertionSeverity::Gate : AssertionSeverity::Soft,
            message: $missing ? 'No judge configured.' : '',
        ));
        if (!$missing) {
            $this->evaluate();
        }
    }

    public function on(string $output): self {
        $this->request = new JudgeRequest($this->request->criterion, $output, $this->request->input, $this->request->reference);
        if ($this->judge !== null) {
            $this->evaluate();
        }
        return $this;
    }

    public function gate(): self {
        $this->handle->gate();
        return $this;
    }

    public function soft(): self {
        $this->handle->soft();
        return $this;
    }

    public function atLeast(float $threshold): self {
        $this->handle->atLeast($threshold);
        return $this;
    }

    public function label(string $label): self {
        $this->handle->label($label);
        return $this;
    }

    private function evaluate(): void {
        try {
            $score = $this->judge?->judge($this->request) ?? new JudgeScore(0.0, 'No judge configured.');
            $this->handle->replace($this->handle->result()->withScore($score->score, $score->reason));
        } catch (Throwable $error) {
            $failed = $this->handle->result()
                ->withScore(0.0, 'Judge failed: ' . $error->getMessage())
                ->withSeverity(AssertionSeverity::Gate);
            $this->handle->replace($failed);
        }
    }
}
