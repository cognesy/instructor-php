<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Override;
use PHPUnit\Framework\Assert;
use RuntimeException;

final class PHPUnitEvalReporter implements CanFailAgentEvalTestSuite
{
    public static function default(): self {
        return new self();
    }

    #[Override]
    public function id(): string {
        return 'phpunit';
    }

    #[Override]
    public function onRunStarted(int $caseCount): void {}

    #[Override]
    public function onEvalCompleted(EvalResult $result): void {}

    #[Override]
    public function onRunCompleted(EvalRunResult $result): void {
        if (!class_exists(Assert::class)) {
            throw new RuntimeException('PHPUnitEvalReporter requires phpunit/phpunit as a development dependency.');
        }
        Assert::assertSame(
            EvalExitCode::Success,
            $result->exitCode(),
            EvalTestFailureMessage::fromResult($result),
        );
    }
}
