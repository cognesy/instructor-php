<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Integration\Evals\Runner;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Evals\AgentEval;
use Cognesy\Agents\Evals\AgentEvals;
use Cognesy\Agents\Evals\EvalApplication;
use Cognesy\Agents\Evals\EvalContext;
use Cognesy\Agents\Evals\EvalExitCode;
use Cognesy\Agents\Evals\EvalRunner;
use Cognesy\Agents\Evals\EvalRunOptions;
use Cognesy\Agents\Evals\EvalVerdict;
use Cognesy\Agents\Evals\LocalAgentTarget;

function runnerTarget(): LocalAgentTarget {
    return LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()->withCapability(new UseDriver(FakeAgentDriver::fromResponses('ok')))->build());
}

it('runs in suite order and derives pass fail scored and skipped verdicts', function (): void {
    $suite = new AgentEvals(
        AgentEval::define('pass', static function (EvalContext $t): void {
            $t->send('x');
            $t->succeeded();
        })->withId('01-pass'),
        AgentEval::define('scored', static function (EvalContext $t): void { $t->check('quality', false)->soft(); })->withId('02-scored'),
        AgentEval::define('failed', static function (EvalContext $t): void { $t->check('gate', false); })->withId('03-failed'),
        AgentEval::define('skip', static function (EvalContext $t): void { $t->skip('not configured'); })->withId('04-skip'),
    );
    $result = (new EvalRunner(runnerTarget()))->run($suite);

    expect(array_map(static fn ($item) => $item->id(), $result->all()))->toBe(['01-pass', '02-scored', '03-failed', '04-skip'])
        ->and(array_map(static fn ($item) => $item->verdict(), $result->all()))->toBe([EvalVerdict::Passed, EvalVerdict::Scored, EvalVerdict::Failed, EvalVerdict::Skipped])
        ->and($result->exitCode())->toBe(EvalExitCode::EvalFailure);
});

it('promotes scored results only in strict mode', function (): void {
    $suite = new AgentEvals(AgentEval::define('score', static function (EvalContext $t): void { $t->check('quality', false)->soft(); })->withId('score'));
    $result = (new EvalRunner(runnerTarget()))->run($suite, EvalRunOptions::default()->withStrict(true));
    expect($result->exitCode())->toBe(EvalExitCode::EvalFailure)
        ->and($result->exitCode(false))->toBe(EvalExitCode::Success);
});

it('fails a case that exceeds its cooperative timeout', function (): void {
    $suite = new AgentEvals(AgentEval::define('slow', static function (): void { usleep(5_000); })->withId('slow'));
    $result = (new EvalRunner(runnerTarget()))->run($suite, EvalRunOptions::default()->withTimeout(0.000_001));

    expect($result->all()[0]->verdict())->toBe(EvalVerdict::Failed)
        ->and($result->all()[0]->error())->toContain('cooperative timeout');
});

it('lists cases and returns configuration exit code for invalid CLI options', function (): void {
    $root = sys_get_temp_dir() . '/eval-cli-' . bin2hex(random_bytes(4));
    mkdir($root, 0777, true);
    file_put_contents($root . '/smoke.eval.php', "<?php return \\Cognesy\\Agents\\Evals\\AgentEval::define('smoke', static function(): void {});");
    $output = '';
    $error = '';
    $help = '';
    $application = new EvalApplication();

    $listed = $application->run(['agents-eval', $root, '--list'], function (string $text) use (&$output): void { $output .= $text; });
    $helped = $application->run(['agents-eval', '--help'], function (string $text) use (&$help): void { $help .= $text; });
    $invalid = $application->run(['agents-eval', '--wat'], stderr: function (string $text) use (&$error): void { $error .= $text; });

    expect($listed)->toBe(EvalExitCode::Success->value)
        ->and($output)->toBe("smoke\n")
        ->and($helped)->toBe(EvalExitCode::Success->value)
        ->and($help)->toContain('Usage: agents-eval', '--timeout=<seconds>')
        ->and($invalid)->toBe(EvalExitCode::ConfigurationError->value)
        ->and($error)->toContain('Unknown option');
    unlink($root . '/smoke.eval.php');
    rmdir($root);
});

it('accepts junit and timeout CLI options', function (): void {
    $root = sys_get_temp_dir() . '/eval-cli-report-' . bin2hex(random_bytes(4));
    mkdir($root, 0777, true);
    file_put_contents($root . '/smoke.eval.php', "<?php return \\Cognesy\\Agents\\Evals\\AgentEval::define('smoke', static function(): void {});");
    file_put_contents($root . '/evals.config.php', <<<'PHP'
<?php
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Evals\EvalConfig;
use Cognesy\Agents\Evals\LocalAgentTarget;
return EvalConfig::default()->withTarget(LocalAgentTarget::fromFactory(
    static fn() => AgentBuilder::base()->withCapability(new UseDriver(FakeAgentDriver::fromResponses('ok')))->build(),
));
PHP);
    $junit = $root . '/junit.xml';

    $exit = (new EvalApplication())->run(['agents-eval', $root, '--timeout=1', '--junit=' . $junit]);

    expect($exit)->toBe(EvalExitCode::Success->value)
        ->and(file_get_contents($junit))->toContain('smoke');
    unlink($junit);
    unlink($root . '/evals.config.php');
    unlink($root . '/smoke.eval.php');
    rmdir($root);
});
