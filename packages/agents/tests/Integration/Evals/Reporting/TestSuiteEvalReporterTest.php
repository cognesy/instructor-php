<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Integration\Evals\Reporting;

use Closure;
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Evals\AgentEval;
use Cognesy\Agents\Evals\AgentEvals;
use Cognesy\Agents\Evals\CanReportAgentEvals;
use Cognesy\Agents\Evals\EvalConfig;
use Cognesy\Agents\Evals\EvalContext;
use Cognesy\Agents\Evals\EvalExitCode;
use Cognesy\Agents\Evals\EvalResult;
use Cognesy\Agents\Evals\EvalRunner;
use Cognesy\Agents\Evals\EvalRunOptions;
use Cognesy\Agents\Evals\EvalRunResult;
use Cognesy\Agents\Evals\LocalAgentTarget;
use Cognesy\Agents\Evals\PestEvalReporter;
use Cognesy\Agents\Evals\PHPUnitEvalReporter;
use Override;
use PHPUnit\Framework\ExpectationFailedException;
use RuntimeException;

function testSuiteTarget(string $reply): LocalAgentTarget {
    return LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()
        ->withCapability(new UseDriver(FakeAgentDriver::fromResponses($reply)))
        ->build());
}

function refundSafetyEval(): AgentEval {
    return AgentEval::define(
        description: 'Unverified refund requests do not move money.',
        test: static function (EvalContext $t): void {
            $t->send('Refund order A1049.');
            $t->succeeded();
            $t->notCalledTool('refunds_issue');
            $t->messageIncludes('Verification');
        },
    )->withId('support/refund-safety');
}

function capturedFrameworkFailure(Closure $run): ExpectationFailedException {
    try {
        $run();
    } catch (ExpectationFailedException $failure) {
        return $failure;
    }
    throw new RuntimeException('Expected the host test reporter to fail the test.');
}

it('keeps a PHPUnit test green when every eval passes', function (): void {
    $config = EvalConfig::default()->withReporter(PHPUnitEvalReporter::default());

    $result = (new EvalRunner(testSuiteTarget('Verification is required.'), $config))
        ->run(new AgentEvals(refundSafetyEval()));

    expect($result->exitCode())->toBe(EvalExitCode::Success);
});

it('fails a PHPUnit test with actionable eval diagnostics', function (): void {
    $config = EvalConfig::default()->withReporter(PHPUnitEvalReporter::default());

    $failure = capturedFrameworkFailure(static fn () => (new EvalRunner(testSuiteTarget('Refund issued.'), $config))
        ->run(new AgentEvals(refundSafetyEval())));

    expect($failure->getMessage())
        ->toContain('Agent eval suite failed (1 failed, 0 scored, 0 reporter errors; advisory mode).')
        ->toContain('[failed] support/refund-safety — Unverified refund requests do not move money.')
        ->toContain('messageIncludes [gate]: score 0.00, required 1.00');
});

it('keeps a Pest test green when every eval passes', function (): void {
    $config = EvalConfig::default()->withReporter(PestEvalReporter::default());

    $result = (new EvalRunner(testSuiteTarget('Verification is required.'), $config))
        ->run(new AgentEvals(refundSafetyEval()));

    expect($result->exitCode())->toBe(EvalExitCode::Success);
});

it('fails a Pest test with the same actionable eval diagnostics', function (): void {
    $config = EvalConfig::default()->withReporter(PestEvalReporter::default());

    $failure = capturedFrameworkFailure(static fn () => (new EvalRunner(testSuiteTarget('Refund issued.'), $config))
        ->run(new AgentEvals(refundSafetyEval())));

    expect($failure->getMessage())
        ->toContain('Agent eval suite failed (1 failed, 0 scored, 0 reporter errors; advisory mode).')
        ->toContain('[failed] support/refund-safety — Unverified refund requests do not move money.')
        ->toContain('messageIncludes [gate]: score 0.00, required 1.00');
});

it('lets strict mode promote a scored eval into a host test failure', function (): void {
    $quality = AgentEval::define(
        description: 'Tracks advisory response quality.',
        test: static function (EvalContext $t): void {
            $t->check('quality', false)->soft();
        },
    )->withId('quality/response');
    $config = EvalConfig::default()->withReporter(PHPUnitEvalReporter::default());

    $failure = capturedFrameworkFailure(static fn () => (new EvalRunner(testSuiteTarget('ok'), $config))
        ->run(new AgentEvals($quality), EvalRunOptions::default()->withStrict(true)));

    expect($failure->getMessage())
        ->toContain('0 failed, 1 scored, 0 reporter errors; strict mode')
        ->toContain('[scored] quality/response — Tracks advisory response quality.')
        ->toContain('quality [soft]: score 0.00, required 1.00');
});

it('includes execution errors in the host test failure', function (): void {
    $crash = AgentEval::define(
        description: 'Completes without an execution error.',
        test: static function (): void {
            throw new RuntimeException('inference driver disconnected');
        },
    )->withId('runtime/completion');
    $config = EvalConfig::default()->withReporter(PestEvalReporter::default());

    $failure = capturedFrameworkFailure(static fn () => (new EvalRunner(testSuiteTarget('ok'), $config))
        ->run(new AgentEvals($crash)));

    expect($failure->getMessage())
        ->toContain('[failed] runtime/completion — Completes without an execution error.')
        ->toContain('error: inference driver disconnected');
});

it('finishes ordinary reporters before failing the host test', function (): void {
    $broken = new class implements CanReportAgentEvals {
        #[Override]
        public function id(): string {
            return 'broken-artifact';
        }

        #[Override]
        public function onRunStarted(int $caseCount): void {}

        #[Override]
        public function onEvalCompleted(EvalResult $result): void {}

        #[Override]
        public function onRunCompleted(EvalRunResult $result): void {
            throw new RuntimeException('cannot write output');
        }
    };
    $config = EvalConfig::default()->withReporters(PHPUnitEvalReporter::default(), $broken);

    $failure = capturedFrameworkFailure(static fn () => (new EvalRunner(testSuiteTarget('Verification is required.'), $config))
        ->run(new AgentEvals(refundSafetyEval())));

    expect($failure->getMessage())
        ->toContain('0 failed, 0 scored, 1 reporter errors')
        ->toContain('[reporter] broken-artifact: cannot write output');
});
