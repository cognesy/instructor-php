<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Integration\Evals\Reporting;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Evals\AgentEval;
use Cognesy\Agents\Evals\AgentEvals;
use Cognesy\Agents\Evals\EvalConfig;
use Cognesy\Agents\Evals\EvalContext;
use Cognesy\Agents\Evals\EvalExitCode;
use Cognesy\Agents\Evals\EvalRunner;
use Cognesy\Agents\Evals\LocalAgentTarget;
use Cognesy\Agents\Evals\PHPUnitEvalReporter;
use PHPUnit\Framework\TestCase;

final class PHPUnitEvalReporterCompatibilityTest extends TestCase
{
    public function testPassingEvalIsAPassingPHPUnitAssertion(): void {
        $target = LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()
            ->withCapability(new UseDriver(FakeAgentDriver::fromResponses('Verification is required.')))
            ->build());
        $eval = AgentEval::define(
            description: 'Refunds require verification.',
            test: static function (EvalContext $t): void {
                $t->send('Refund order A1049.');
                $t->succeeded();
                $t->messageIncludes('Verification');
            },
        )->withId('phpunit/refund-safety');
        $config = EvalConfig::default()->withReporter(PHPUnitEvalReporter::default());

        $result = (new EvalRunner($target, $config))->run(new AgentEvals($eval));

        self::assertSame(EvalExitCode::Success, $result->exitCode());
    }
}
