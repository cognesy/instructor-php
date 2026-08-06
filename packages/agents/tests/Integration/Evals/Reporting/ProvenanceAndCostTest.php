<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Integration\Evals\Reporting;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Evals\AgentEval;
use Cognesy\Agents\Evals\AgentEvals;
use Cognesy\Agents\Evals\AgentLoopJudge;
use Cognesy\Agents\Evals\AgentRun;
use Cognesy\Agents\Evals\ConsoleEvalReporter;
use Cognesy\Agents\Evals\EvalConfig;
use Cognesy\Agents\Evals\EvalContext;
use Cognesy\Agents\Evals\EvalRunner;
use Cognesy\Agents\Evals\EvalRunOptions;
use Cognesy\Agents\Evals\EvalTestFailureMessage;
use Cognesy\Agents\Evals\FakeAgentJudge;
use Cognesy\Agents\Evals\JudgeScore;
use Cognesy\Agents\Evals\LocalAgentTarget;
use Cognesy\Agents\Tests\Support\LlmAwareFakeDriver;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;

function provenanceTarget(): LocalAgentTarget {
    $config = new LLMConfig(model: 'gpt-5-target', driver: 'openai');
    $driver = new LlmAwareFakeDriver(
        FakeAgentDriver::fromSteps(ScenarioStep::final('Refund denied pending verification.', new InferenceUsage(100, 42))),
        $config,
    );
    return LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()->withCapability(new UseDriver($driver))->build());
}

/** A judge with no `UseGuards` capability, so a `JudgeGuardsNotConfigured` warning fires on its own run. */
function unguardedJudge(float $score, string $reason, array $evidence = []): AgentLoopJudge {
    $config = new LLMConfig(model: 'gpt-5-judge', driver: 'openai');
    $driver = new LlmAwareFakeDriver(
        FakeAgentDriver::fromSteps(ScenarioStep::toolCall('submit_judgment', [
            'score' => $score,
            'reason' => $reason,
            'evidence' => $evidence,
        ], usage: new InferenceUsage(10, 5))),
        $config,
    );
    return AgentLoopJudge::fromBuilder(fn () => AgentBuilder::base()->withCapability(new UseDriver($driver)));
}

/**
 * A `CanJudgeAgentEval` that is deliberately NOT `AgentLoopJudge`, but still
 * returns a `JudgeScore` carrying an `AgentRun`. A provenance implementation
 * that infers `judge.class` from "an assertion carries a judge run" rather
 * than observing the real judge instance would misreport this as
 * `AgentLoopJudge::class`; only threading the real class through
 * `JudgeExpectation::resolve()` gets it right.
 */
function nonAgentLoopJudge(float $score, string $reason): FakeAgentJudge {
    return FakeAgentJudge::fromClosure(
        fn () => new JudgeScore($score, $reason, run: AgentRun::empty()),
    );
}

it('reports the real judge class for a non-AgentLoopJudge judge that still carries a run, never a hardcoded AgentLoopJudge guess', function (): void {
    $judge = nonAgentLoopJudge(0.7, 'plausible but unverifiable');
    $config = EvalConfig::default()->withJudge($judge)->withTarget(provenanceTarget());
    $eval = AgentEval::define('Judge class must be observed, not assumed.', static function (EvalContext $t): void {
        $t->send('Refund order A1049.');
        $t->judge()->closedQa('Does the reply require verification?')->atLeast(0.0);
    })->withId('provenance/non-agentloop-judge-class');

    $result = (new EvalRunner(config: $config))->run(new AgentEvals($eval));
    $provenance = $result->all()[0]->provenance();

    expect($provenance['judge']['class'])->toBe(FakeAgentJudge::class)
        ->and($provenance['judge']['class'])->not->toBe(AgentLoopJudge::class);
});

it('separates target and judge tokens, and never folds judge cost into target cost', function (): void {
    $judge = unguardedJudge(0.4, 'insufficient verification evidence', ['no verification step observed']);
    $config = EvalConfig::default()->withJudge($judge)->withTarget(provenanceTarget());
    $eval = AgentEval::define('Refund replies require verification.', static function (EvalContext $t): void {
        $t->send('Refund order A1049.');
        $t->judge()->closedQa('Does the reply require verification?')->atLeast(1.0);
    })->withId('provenance/tokens');

    $result = (new EvalRunner(config: $config))->run(new AgentEvals($eval));
    $evalResult = $result->all()[0];

    expect($evalResult->tokens())->toBe(['target' => 142, 'judge' => 15, 'total' => 157])
        ->and($result->tokens())->toBe(['target' => 142, 'judge' => 15, 'total' => 157]);
});

it('reports target and judge provenance honestly: resolved LLM configs, structural judge class, null temperature, and an observed guard warning', function (): void {
    $judge = unguardedJudge(0.4, 'insufficient verification evidence', ['no verification step observed']);
    $config = EvalConfig::default()->withJudge($judge)->withTarget(provenanceTarget());
    $eval = AgentEval::define('Refund replies require verification.', static function (EvalContext $t): void {
        $t->send('Refund order A1049.');
        $t->judge()->closedQa('Does the reply require verification?')->atLeast(1.0);
    })->withId('provenance/shape');

    $result = (new EvalRunner(config: $config))->run(new AgentEvals($eval));
    $provenance = $result->all()[0]->provenance();

    expect($provenance['target'])->toBe([
        'driver' => 'openai',
        'model' => 'gpt-5-target',
        'maxTokens' => 1024,
        'contextLength' => 8000,
        'maxOutputLength' => 4096,
    ])
        ->and($provenance['judge']['class'])->toBe(AgentLoopJudge::class)
        ->and($provenance['judge']['llm']['model'])->toBe('gpt-5-judge')
        ->and($provenance['judge']['temperature'])->toBeNull()
        ->and($provenance['judge']['guardsWarningObserved'])->toBeTrue()
        ->and($result->provenance())->toBe($provenance);
});

it('reports no judge provenance and a null judge temperature caveat when no assertion carries a judge run', function (): void {
    $config = EvalConfig::default()->withTarget(provenanceTarget());
    $eval = AgentEval::define('No judge involved.', static function (EvalContext $t): void {
        $t->send('Refund order A1049.');
        $t->succeeded();
    })->withId('provenance/no-judge');

    $result = (new EvalRunner(config: $config))->run(new AgentEvals($eval));
    $provenance = $result->all()[0]->provenance();

    expect($provenance['judge'])->toBeNull()
        ->and($provenance['target']['model'])->toBe('gpt-5-target')
        ->and($result->all()[0]->tokens())->toBe(['target' => 142, 'judge' => 0, 'total' => 142]);
});

it('prints one TARGET line, a JUDGE line per judged assertion with its own EVIDENCE lines, and a single TOKENS footer in verbose mode', function (): void {
    $judge = unguardedJudge(0.4, 'insufficient verification evidence', ['no verification step observed', 'policy requires order-owner check']);
    $config = EvalConfig::default()->withJudge($judge)->withTarget(provenanceTarget());
    $console = '';
    $config = $config->withReporter(ConsoleEvalReporter::fromWriter(function (string $text) use (&$console): void { $console .= $text; }));
    $eval = AgentEval::define('Refund replies require verification.', static function (EvalContext $t): void {
        $t->send('Refund order A1049.');
        $t->judge()->closedQa('Does the reply require verification?')->atLeast(1.0);
    })->withId('provenance/console');

    (new EvalRunner(config: $config))->run(new AgentEvals($eval), EvalRunOptions::default()->withVerbose(true));

    expect($console)->toContain('TARGET steps=1 tools=0 tokens=142 stop=')
        ->and($console)->toContain('JUDGE score=0.40 steps=1 tools=1 tokens=15')
        ->and($console)->toContain('EVIDENCE no verification step observed')
        ->and($console)->toContain('EVIDENCE policy requires order-owner check')
        ->and(substr_count($console, 'TOKENS target='))->toBe(1)
        ->and($console)->toContain('TOKENS target=142 judge=15 total=157');
});

it('includes judge evidence and target step/stop context in the PHPUnit/Pest failure message without needing artifact inspection', function (): void {
    $judge = unguardedJudge(0.4, 'insufficient verification evidence', ['no verification step observed']);
    $config = EvalConfig::default()->withJudge($judge)->withTarget(provenanceTarget());
    $eval = AgentEval::define('Refund replies require verification.', static function (EvalContext $t): void {
        $t->send('Refund order A1049.');
        $t->judge()->closedQa('Does the reply require verification?')->atLeast(1.0);
    })->withId('provenance/failure-message');

    $result = (new EvalRunner(config: $config))->run(new AgentEvals($eval), EvalRunOptions::default()->withStrict(true));
    $message = EvalTestFailureMessage::fromResult($result);

    expect($message)->toContain('- target: steps=1 stop=')
        ->and($message)->toContain('- evidence: no verification step observed');
});
