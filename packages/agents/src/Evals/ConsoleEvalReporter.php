<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Closure;
use Override;

final readonly class ConsoleEvalReporter implements CanReportAgentEvals
{
    /** @param Closure(string): void $write */
    public function __construct(private Closure $write, private bool $verbose = false) {}

    /** @param Closure(string): void $write */
    public static function fromWriter(Closure $write, bool $verbose = false): self {
        return new self($write, $verbose);
    }

    public function withVerbose(bool $verbose): self {
        return new self($this->write, $verbose);
    }

    #[Override]
    public function id(): string {
        return 'console';
    }

    #[Override]
    public function onRunStarted(int $caseCount): void {
        ($this->write)("Running {$caseCount} agent evals\n");
    }

    #[Override]
    public function onEvalCompleted(EvalResult $result): void {
        $repetition = $result->repetition();
        if ($repetition === null) {
            ($this->write)(sprintf("[%s] %s (%.1fms)\n", strtoupper($result->verdict()->value), $result->id(), $result->duration() * 1000));
        } else {
            ($this->write)(self::repetitionLine($result, $repetition));
        }
        if ($result->error() !== null) {
            ($this->write)("  ERROR {$result->error()}\n");
        }
        if ($result->skipReason() !== null) {
            ($this->write)("  SKIP {$result->skipReason()}\n");
        }
        if ($this->verbose && $repetition !== null) {
            foreach (self::trialLines($repetition) as $line) {
                ($this->write)($line);
            }
        }
        if ($this->verbose) {
            ($this->write)('  ' . self::targetLine($result->run()) . "\n");
        }
        foreach ($result->assertions() as $assertion) {
            if ($assertion->passed() && !$this->verbose) {
                continue;
            }
            ($this->write)(sprintf("  %s %s score=%.2f threshold=%.2f%s\n", $assertion->passed() ? 'PASS' : 'FAIL', $assertion->label() ?? $assertion->name(), $assertion->score(), $assertion->threshold() ?? 1.0, $assertion->message() !== '' ? ' ' . $assertion->message() : ''));
            $judgeScore = $assertion->judgeScore();
            if ($this->verbose && $judgeScore !== null) {
                ($this->write)('  ' . self::judgeLine($judgeScore) . "\n");
                foreach ($judgeScore->evidence as $evidence) {
                    ($this->write)("    EVIDENCE {$evidence}\n");
                }
            }
        }
        if ($this->verbose) {
            foreach ($result->logs() as $log) {
                ($this->write)('  LOG ' . $log->message() . ' ' . json_encode($log->context(), JSON_THROW_ON_ERROR) . "\n");
            }
        }
    }

    #[Override]
    public function onRunCompleted(EvalRunResult $result): void {
        $counts = ['passed' => 0, 'failed' => 0, 'scored' => 0, 'skipped' => 0];
        foreach ($result->all() as $evalResult) {
            $counts[$evalResult->verdict()->value]++;
        }
        ($this->write)(sprintf("passed=%d failed=%d scored=%d skipped=%d\n", $counts['passed'], $counts['failed'], $counts['scored'], $counts['skipped']));

        $tokens = $result->tokens();
        ($this->write)(sprintf("TOKENS target=%d judge=%d total=%d\n", $tokens['target'], $tokens['judge'], $tokens['total']));
    }

    // INTERNAL ////////////////////////////////////////////////

    /**
     * A repeated case reports a rate rather than a verdict, because that is what
     * was measured: `PASS 4/5  refund-requires-verification  judge=0.88+/-0.06`.
     * The deviation is the population deviation of the judged scores (see
     * `EvalRepetition::judgeScoreStdDev()`); the `judge=` field is omitted when
     * nothing in the case was judged, rather than printing a fabricated 0.00.
     * The duration is the total across trials, in the same format the
     * single-trial line uses.
     */
    private static function repetitionLine(EvalResult $result, EvalRepetition $repetition): string {
        $mean = $repetition->judgeScoreMean();
        return sprintf(
            "%s %d/%d  %s%s  (%.1fms)\n",
            self::rateLabel($result->verdict()),
            $repetition->passCount(),
            $repetition->trialCount(),
            $result->id(),
            $mean === null ? '' : sprintf('  judge=%.2f+/-%.2f', $mean, $repetition->judgeScoreStdDev() ?? 0.0),
            $result->duration() * 1000,
        );
    }

    /** @return list<string> */
    private static function trialLines(EvalRepetition $repetition): array {
        $lines = [];
        $total = $repetition->trialCount();
        foreach ($repetition->trials() as $index => $trial) {
            $judged = self::trialJudgeScores($trial);
            $lines[] = sprintf(
                "  TRIAL %d/%d %s%s\n",
                $index + 1,
                $total,
                $trial->verdict()->value,
                $judged === [] ? '' : ' judge=' . implode(',', array_map(static fn (float $score): string => sprintf('%.2f', $score), $judged)),
            );
        }
        return $lines;
    }

    /** @return list<float> */
    private static function trialJudgeScores(EvalResult $trial): array {
        $scores = [];
        foreach ($trial->assertions()->all() as $assertion) {
            $judgeScore = $assertion->judgeScore();
            if ($judgeScore !== null) {
                $scores[] = $judgeScore->score;
            }
        }
        return $scores;
    }

    private static function rateLabel(EvalVerdict $verdict): string {
        return match ($verdict) {
            EvalVerdict::Passed => 'PASS',
            EvalVerdict::Failed => 'FAIL',
            EvalVerdict::Scored => 'SCORED',
            EvalVerdict::Skipped => 'SKIP',
        };
    }

    private static function targetLine(AgentRun $run): string {
        return sprintf(
            'TARGET steps=%d tools=%d tokens=%d stop=%s',
            $run->stepCount(),
            $run->tools()->count(),
            $run->usage()->total(),
            $run->stopSignal()?->reason->value ?? 'none',
        );
    }

    private static function judgeLine(JudgeScore $score): string {
        if ($score->run === null) {
            return sprintf('JUDGE score=%.2f', $score->score);
        }
        return sprintf(
            'JUDGE score=%.2f steps=%d tools=%d tokens=%d',
            $score->score,
            $score->run->stepCount(),
            $score->run->tools()->count(),
            $score->run->usage()->total(),
        );
    }
}
