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
        if ($result->error() !== null) {
            $lines[] = '  - error: ' . $result->error();
        }
        foreach ($result->assertions() as $assertion) {
            if ($assertion->passed()) {
                continue;
            }
            $lines[] = self::assertionLine($assertion);
        }
        return $lines;
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
