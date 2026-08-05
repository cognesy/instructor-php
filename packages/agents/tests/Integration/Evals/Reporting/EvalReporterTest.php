<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Integration\Evals\Reporting;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Evals\AgentEval;
use Cognesy\Agents\Evals\AgentEvals;
use Cognesy\Agents\Evals\ArtifactEvalReporter;
use Cognesy\Agents\Evals\ConsoleEvalReporter;
use Cognesy\Agents\Evals\EvalConfig;
use Cognesy\Agents\Evals\EvalContext;
use Cognesy\Agents\Evals\EvalRunner;
use Cognesy\Agents\Evals\EvalRunOptions;
use Cognesy\Agents\Evals\JUnitEvalReporter;
use Cognesy\Agents\Evals\LocalAgentTarget;

it('emits console junit and native artifacts from the same verdict', function (): void {
    $root = sys_get_temp_dir() . '/eval-report-' . bin2hex(random_bytes(4));
    $console = '';
    $artifacts = new ArtifactEvalReporter($root . '/artifacts');
    $config = EvalConfig::default()->withReporters(
        ConsoleEvalReporter::fromWriter(function (string $text) use (&$console): void { $console .= $text; }),
        new JUnitEvalReporter($root . '/junit.xml'),
        $artifacts,
    );
    $target = LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()->withCapability(new UseDriver(FakeAgentDriver::fromResponses('ok')))->build());
    $suite = new AgentEvals(
        AgentEval::define('pass', static function (EvalContext $t): void {
            $t->send('x');
            $t->succeeded();
            $t->log('trajectory captured', ['case' => 'pass']);
        })->withId('support/pass'),
        AgentEval::define('scored', static function (EvalContext $t): void { $t->check('quality', false)->soft(); })->withId('support/scored'),
    );

    $result = (new EvalRunner($target, $config))->run($suite, EvalRunOptions::default()->withStrict(true)->withVerbose(true));

    expect($result->all()[0])->toHavePassedEval()
        ->and($console)->toContain('[PASSED] support/pass')
        ->and($console)->toContain('PASS succeeded')
        ->and($console)->toContain('LOG trajectory captured')
        ->and(file_get_contents($root . '/junit.xml'))->toContain('support/pass')
        ->and(file_get_contents($root . '/junit.xml'))->toContain('scored eval failed in strict mode')
        ->and(is_file($artifacts->runDirectory() . '/summary.json'))->toBeTrue()
        ->and(is_file($artifacts->runDirectory() . '/evals/support/pass/details.json'))->toBeTrue()
        ->and(file_get_contents($artifacts->runDirectory() . '/evals/support/pass/events.ndjson'))->toContain('AgentExecutionCompleted');
});
