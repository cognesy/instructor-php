<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Override;
use Pest\Expectation;
use RuntimeException;

final class PestEvalReporter implements CanFailAgentEvalTestSuite
{
    public static function default(): self {
        return new self();
    }

    #[Override]
    public function id(): string {
        return 'pest';
    }

    #[Override]
    public function onRunStarted(int $caseCount): void {}

    #[Override]
    public function onEvalCompleted(EvalResult $result): void {}

    #[Override]
    public function onRunCompleted(EvalRunResult $result): void {
        if (!class_exists(Expectation::class)) {
            throw new RuntimeException('PestEvalReporter requires pestphp/pest as a development dependency.');
        }
        (new Expectation($result->exitCode()))->toBe(
            EvalExitCode::Success,
            EvalTestFailureMessage::fromResult($result),
        );
    }
}
