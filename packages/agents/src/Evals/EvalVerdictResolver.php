<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

final readonly class EvalVerdictResolver
{
    public function resolve(
        AssertionResults $assertions,
        bool $skipped = false,
        ?string $error = null,
    ): EvalVerdict {
        return match (true) {
            $error !== null => EvalVerdict::Failed,
            $assertions->hasFailedGate() => EvalVerdict::Failed,
            $skipped => EvalVerdict::Skipped,
            $assertions->hasFailedSoft() => EvalVerdict::Scored,
            default => EvalVerdict::Passed,
        };
    }

    /**
     * k-of-N verdict for a case that ran more than once: passed when at least
     * `requiredPasses()` of its trials came back `Passed`.
     *
     * A trial counts towards the rate only when its own verdict is `Passed`, so
     * a soft-scored trial counts as a miss. That makes `--pass-rate` an explicit
     * gate on the case - the operator asked for a rate, and a case that misses
     * it fails rather than degrading to `Scored`. This is the one place where a
     * soft assertion can produce a hard failure, and it is intentional: without
     * it, `--repeat=5 --pass-rate=0.8` could not fail anything in advisory mode
     * and would measure nothing.
     *
     * A case whose every trial skipped is `Skipped`: a skip states that the case
     * could not run at all (unconfigured environment), which repetition does not
     * turn into a measurement. Trials that skipped alongside trials that ran
     * count as misses like any other non-pass.
     */
    public function resolveRepeated(EvalRepetition $repetition): EvalVerdict {
        return match (true) {
            $repetition->allSkipped() => EvalVerdict::Skipped,
            $repetition->satisfied() => EvalVerdict::Passed,
            default => EvalVerdict::Failed,
        };
    }

    /**
     * Guards `ceil(passRate * trials)` against binary floating-point error.
     * `0.07 * 100` is 7.0000000000000009 in IEEE 754, so a bare `ceil()` returns
     * 8 and demands one more pass than the operator asked for. (Not every such
     * product is inexact - `0.7 * 10` happens to land on exactly 7.0 - which is
     * why the guard cannot be reasoned about from one example and is applied
     * unconditionally.) Subtracting a small absolute tolerance before rounding up
     * collapses such near-integer products onto the integer they were meant to
     * be, while a genuinely fractional product (`0.7 * 3` = 2.1, which really
     * does need 3 passes) is unaffected.
     * The tolerance is many orders of magnitude larger than the representation
     * error at these magnitudes and many orders smaller than any meaningful step
     * in the product, for any trial count a repeated eval would plausibly use.
     *
     * The result is always at least 1 - a pass rate above 0 can never be
     * satisfied by zero passing trials - and never more than the trial count.
     */
    public static function requiredPasses(int $trials, float $passRate): int {
        $required = (int) ceil(($passRate * $trials) - 1e-9);
        return max(1, min($trials, $required));
    }
}
