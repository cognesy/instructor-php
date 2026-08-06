<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Throwable;

/**
 * Fluent judge assertion. The chain (`on`, `gate`, `soft`, `atLeast`, `label`)
 * only accumulates state - it never invokes the judge. The judge runs at most
 * once, on first read of the recorded `AssertionResult`: collector
 * finalization (`AssertionCollector::results()`), a handle read
 * (`AssertionCollector::at()`), or a reporter walking either of those. A chain
 * whose result is never read invokes the judge zero times.
 */
final class JudgeExpectation
{
    private JudgeRequest $request;
    private AssertionSeverity $severity;
    private ?float $threshold = null;
    private ?string $label = null;

    public function __construct(
        private readonly ?CanJudgeAgentEval $judge,
        JudgeRequest $request,
        AssertionCollector $collector,
    ) {
        $this->request = $request;
        $this->severity = $judge === null ? AssertionSeverity::Gate : AssertionSeverity::Soft;
        $collector->recordLazy(
            placeholder: new AssertionResult(name: $this->name(), score: 0.0, severity: $this->severity),
            resolve: fn (): AssertionResult => $this->resolve(),
        );
    }

    /** Replaces the graded output only; the target run carried by the request is retained. */
    public function on(string $output): self {
        $this->request = new JudgeRequest(
            criterion: $this->request->criterion,
            output: $output,
            run: $this->request->run,
            input: $this->request->input,
            reference: $this->request->reference,
        );
        return $this;
    }

    public function gate(): self {
        $this->severity = AssertionSeverity::Gate;
        return $this;
    }

    public function soft(): self {
        $this->severity = AssertionSeverity::Soft;
        return $this;
    }

    public function atLeast(float $threshold): self {
        $this->threshold = $threshold;
        return $this;
    }

    public function label(string $label): self {
        $this->label = $label;
        return $this;
    }

    // INTERNAL ////////////////////////////////////////////////

    /** Runs the judge exactly once, at first call; the collector never calls back in. */
    private function resolve(): AssertionResult {
        if ($this->judge === null) {
            return $this->toResult(0.0, 'No judge configured.', $this->severity, null);
        }
        try {
            $score = $this->judge->judge($this->request);
            return $this->toResult($score->score, $score->reason, $this->severity, $score, $this->judge::class);
        } catch (Throwable $error) {
            // A judge exception is always a gate failure, regardless of any prior gate()/soft() call.
            // No JudgeScore was produced, so there is nothing to attribute a class to.
            return $this->toResult(0.0, 'Judge failed: ' . $error->getMessage(), AssertionSeverity::Gate, null, null);
        }
    }

    private function toResult(float $score, string $message, AssertionSeverity $severity, ?JudgeScore $judgeScore, ?string $judgeClass = null): AssertionResult {
        return new AssertionResult(
            name: $this->name(),
            score: $score,
            severity: $severity,
            threshold: $this->threshold,
            message: $message,
            label: $this->label,
            judgeScore: $judgeScore,
            judgeClass: $judgeClass,
        );
    }

    private function name(): string {
        return 'judge:' . $this->request->criterion;
    }
}
