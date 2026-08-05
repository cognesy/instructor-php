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
}
