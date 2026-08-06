<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

final class EvalTestFailureMessage
{
    public static function fromResult(EvalRunResult $result): string {
        $lines = [self::summary($result)];
        foreach ($result as $eval) {
            if (!self::failsRun($eval, $result->strict())) {
                continue;
            }
            $lines = [...$lines, ...self::evalLines($eval)];
        }
        foreach ($result->reporterErrors() as $error) {
            $lines[] = '- [reporter] ' . $error;
        }
        return implode("\n", $lines);
    }

    private static function summary(EvalRunResult $result): string {
        $failed = self::count($result, EvalVerdict::Failed);
        $scored = self::count($result, EvalVerdict::Scored);
        $mode = $result->strict() ? 'strict' : 'advisory';
        return sprintf(
            'Agent eval suite failed (%d failed, %d scored, %d reporter errors; %s mode).',
            $failed,
            $scored,
            count($result->reporterErrors()),
            $mode,
        );
    }

    /** @return list<string> */
    private static function evalLines(EvalResult $result): array {
        $lines = [sprintf('- [%s] %s — %s', $result->verdict()->value, $result->id(), $result->description())];
        $repetition = $result->repetition();
        if ($repetition !== null) {
            $lines[] = '  - repetition: ' . self::repetitionSummary($repetition);
        }
        if ($result->error() !== null) {
            $lines[] = '  - error: ' . $result->error();
        }
        $lines[] = '  - target: ' . self::targetSummary($result->run());
        foreach ($result->assertions() as $assertion) {
            if ($assertion->passed()) {
                continue;
            }
            $lines[] = self::assertionLine($assertion);
            $lines = [...$lines, ...self::evidenceLines($assertion)];
        }
        return $lines;
    }

    /**
     * Why a repeated case failed, in the terms the operator asked the question
     * in: `passed 3/5, needed 4/5`. A developer reading CI output should not have
     * to infer a missed pass rate from a single trial's score, so the rate comes
     * before the representative trial's assertions - and the judge mean and
     * population deviation come with it, since a case that misses its rate with a
     * wide spread is a different problem from one that misses it consistently.
     */
    private static function repetitionSummary(EvalRepetition $repetition): string {
        $summary = sprintf(
            'passed %d/%d, needed %d/%d',
            $repetition->passCount(),
            $repetition->trialCount(),
            $repetition->requiredPasses(),
            $repetition->trialCount(),
        );
        $mean = $repetition->judgeScoreMean();
        if ($mean === null) {
            return $summary;
        }
        return $summary . sprintf(' (judge mean %.2f +/- %.2f)', $mean, $repetition->judgeScoreStdDev() ?? 0.0);
    }

    private static function assertionLine(AssertionResult $assertion): string {
        $name = $assertion->label() ?? $assertion->name();
        $line = sprintf(
            '  - %s [%s]: score %.2f, required %.2f',
            $name,
            $assertion->severity()->value,
            $assertion->score(),
            $assertion->threshold() ?? 1.0,
        );
        if ($assertion->message() !== '') {
            $line .= ' — ' . $assertion->message();
        }
        return $line;
    }

    /** Concise target run context, so a step/stop-signal-related failure is diagnosable from the failure text alone. */
    private static function targetSummary(AgentRun $run): string {
        return sprintf(
            'steps=%d stop=%s',
            $run->stepCount(),
            $run->stopSignal()?->reason->value ?? 'none',
        );
    }

    /**
     * Judge evidence for a failed judged assertion, so the reason a judge
     * scored low is visible without opening the artifact directory.
     *
     * @return list<string>
     */
    private static function evidenceLines(AssertionResult $assertion): array {
        $judgeScore = $assertion->judgeScore();
        if ($judgeScore === null || $judgeScore->evidence->count() === 0) {
            return [];
        }
        $lines = [];
        foreach ($judgeScore->evidence as $item) {
            $lines[] = '    - evidence: ' . $item;
        }
        return $lines;
    }

    private static function failsRun(EvalResult $result, bool $strict): bool {
        return match ($result->verdict()) {
            EvalVerdict::Failed => true,
            EvalVerdict::Scored => $strict,
            default => false,
        };
    }

    private static function count(EvalRunResult $result, EvalVerdict $verdict): int {
        $count = 0;
        foreach ($result as $eval) {
            if ($eval->verdict() === $verdict) {
                $count++;
            }
        }
        return $count;
    }
}
