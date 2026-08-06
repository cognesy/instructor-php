<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Integration\Evals\Reporting;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\Core\UseGuards;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Evals\AgentEval;
use Cognesy\Agents\Evals\AgentEvals;
use Cognesy\Agents\Evals\AgentLoopJudge;
use Cognesy\Agents\Evals\ArtifactEvalReporter;
use Cognesy\Agents\Evals\EvalConfig;
use Cognesy\Agents\Evals\EvalContext;
use Cognesy\Agents\Evals\EvalRunner;
use Cognesy\Agents\Evals\LocalAgentTarget;
use Cognesy\Agents\Tests\Support\FrozenClock;
use Cognesy\Agents\Tests\Support\LlmAwareFakeDriver;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use DateTimeImmutable;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

function freshArtifactRoot(): string {
    return sys_get_temp_dir() . '/eval-artifacts-' . bin2hex(random_bytes(4));
}

function artifactTarget(string $secretApiKey): LocalAgentTarget {
    // The secret lives only in LLMConfig::$apiKey, never in the agent's reply -
    // this isolates the provenance-specific claim under test: LLMConfigProfile
    // (what target/judge provenance actually embeds) carries driver/model/token
    // limits only, never credentials, regardless of what the target itself says.
    $config = new LLMConfig(model: 'gpt-5-target', driver: 'openai', apiKey: $secretApiKey);
    $driver = new LlmAwareFakeDriver(
        FakeAgentDriver::fromSteps(ScenarioStep::final('ok', new InferenceUsage(20, 8))),
        $config,
    );
    return LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()->withCapability(new UseDriver($driver))->build());
}

function twoAssertionJudge(): AgentLoopJudge {
    $callIndex = 0;
    $scripts = [
        ['score' => 0.3, 'reason' => 'first criterion unmet', 'evidence' => ['first evidence']],
        ['score' => 0.9, 'reason' => 'second criterion met', 'evidence' => ['second evidence']],
    ];
    return AgentLoopJudge::fromBuilder(function () use (&$callIndex, $scripts): object {
        $submission = $scripts[$callIndex] ?? $scripts[array_key_last($scripts)];
        $callIndex++;
        $driver = FakeAgentDriver::fromSteps(ScenarioStep::toolCall('submit_judgment', $submission));
        return AgentBuilder::base()->withCapability(new UseDriver($driver));
    });
}

function guardStateVaryingJudge(): AgentLoopJudge {
    // Same AgentLoopJudge instance answers both assertions. The first judge()
    // call is built without UseGuards (so warnIfGuardsMissing() dispatches
    // JudgeGuardsNotConfigured on THAT call's own AgentRun), the second is
    // built with UseGuards (no warning on its own AgentRun). guardProfile()
    // is last-call-scoped and would report the SECOND call's guard state for
    // both assertions if read post-hoc from shared instance state - this
    // fixture exists to prove per-assertion provenance does not do that.
    $callIndex = 0;
    $scripts = [
        ['score' => 0.5, 'reason' => 'first call, unguarded', 'evidence' => ['unguarded evidence']],
        ['score' => 0.8, 'reason' => 'second call, guarded', 'evidence' => ['guarded evidence']],
    ];
    return AgentLoopJudge::fromBuilder(function () use (&$callIndex, $scripts): object {
        $submission = $scripts[$callIndex] ?? $scripts[array_key_last($scripts)];
        $guarded = $callIndex === 1;
        $callIndex++;
        $driver = FakeAgentDriver::fromSteps(ScenarioStep::toolCall('submit_judgment', $submission));
        $builder = AgentBuilder::base()->withCapability(new UseDriver($driver));
        return $guarded ? $builder->withCapability(new UseGuards(maxSteps: 8, maxTokens: 12_000)) : $builder;
    });
}

it('reports guardsWarningObserved per assertion, not from the judge\'s last call, through the full reporter path', function (): void {
    $root = freshArtifactRoot();
    $reporter = new ArtifactEvalReporter($root);
    $judge = guardStateVaryingJudge();
    $config = EvalConfig::default()->withTarget(artifactTarget('sk-not-a-real-secret'))->withJudge($judge)->withReporter($reporter);
    $eval = AgentEval::define('Guard state varies across judge calls.', static function (EvalContext $t): void {
        $t->send('go');
        // Both assertions are declared before either judge() call actually
        // runs (slice 3 lazy resolution) - they only resolve once the
        // reporter reads $context->assertions(), in this insertion order.
        $t->judge()->closedQa('first criterion, unguarded call?')->atLeast(0.0);
        $t->judge()->summarizes('second criterion, guarded call?')->atLeast(0.0);
    })->withId('cases/guard-state-varies');

    (new EvalRunner(config: $config))->run(new AgentEvals($eval));

    $judgesDirectory = $reporter->runDirectory() . '/evals/cases/guard-state-varies/judges';
    $first = json_decode(file_get_contents($judgesDirectory . '/001.json'), true);
    $second = json_decode(file_get_contents($judgesDirectory . '/002.json'), true);

    expect($first['reason'])->toBe('first call, unguarded')
        ->and($first['run']['guardsWarningObserved'])->toBeTrue()
        ->and($second['reason'])->toBe('second call, guarded')
        ->and($second['run']['guardsWarningObserved'])->toBeFalse();
});

it('writes provenance/tokens into details.json and summary.json, using an injected clock and resolvers so both are reproducible', function (): void {
    $root = freshArtifactRoot();
    $clock = new FrozenClock(new DateTimeImmutable('2026-01-15T10:00:00+00:00'));
    $reporter = new ArtifactEvalReporter(
        root: $root,
        clock: $clock,
        gitShaResolver: static fn (): string => 'deadbeef',
        packageVersionResolver: static fn (): string => '9.9.9-test',
    );
    $config = EvalConfig::default()->withTarget(artifactTarget('sk-not-a-real-secret'))->withReporter($reporter);
    $eval = AgentEval::define('Provenance envelope.', static function (EvalContext $t): void {
        $t->send('go');
        $t->succeeded();
    })->withId('artifacts/envelope');

    (new EvalRunner(config: $config))->run(new AgentEvals($eval));

    $details = json_decode(file_get_contents($reporter->runDirectory() . '/evals/artifacts/envelope/details.json'), true);
    $summary = json_decode(file_get_contents($reporter->runDirectory() . '/summary.json'), true);

    foreach ([$details, $summary] as $document) {
        expect($document['provenance']['package'])->toBe(['version' => '9.9.9-test', 'gitSha' => 'deadbeef'])
            ->and($document['provenance']['startedAt'])->toBe('2026-01-15T10:00:00+00:00')
            ->and($document['provenance']['repeat'])->toBe(1)
            ->and($document['provenance']['target']['model'])->toBe('gpt-5-target');
    }
    expect($details['tokens'])->toBe(['target' => 28, 'judge' => 0, 'total' => 28]);
});

it('degrades gitSha and package version to null, rather than fabricating a value, outside a git checkout and outside Composer', function (): void {
    $root = freshArtifactRoot();
    $reporter = new ArtifactEvalReporter(
        root: $root,
        gitShaResolver: static fn (): ?string => null,
        packageVersionResolver: static fn (): ?string => null,
    );
    $config = EvalConfig::default()->withTarget(artifactTarget('sk-not-a-real-secret'))->withReporter($reporter);
    $eval = AgentEval::define('No git, no composer.', static function (EvalContext $t): void {
        $t->send('go');
        $t->succeeded();
    })->withId('artifacts/degraded');

    (new EvalRunner(config: $config))->run(new AgentEvals($eval));

    $summary = json_decode(file_get_contents($reporter->runDirectory() . '/summary.json'), true);

    expect($summary['provenance']['package'])->toBe(['version' => null, 'gitSha' => null]);
});

it('writes target-trace.json and target-steps.jsonl, and never writes a target-messages.json artifact', function (): void {
    $root = freshArtifactRoot();
    $reporter = new ArtifactEvalReporter($root);
    $config = EvalConfig::default()->withTarget(artifactTarget('sk-not-a-real-secret'))->withReporter($reporter);
    $eval = AgentEval::define('Target trace files.', static function (EvalContext $t): void {
        $t->send('go');
        $t->succeeded();
    })->withId('artifacts/target-trace');

    (new EvalRunner(config: $config))->run(new AgentEvals($eval));

    $evalDirectory = $reporter->runDirectory() . '/evals/artifacts/target-trace';
    $trace = json_decode(file_get_contents($evalDirectory . '/target-trace.json'), true);
    $stepLines = array_filter(explode("\n", trim(file_get_contents($evalDirectory . '/target-steps.jsonl'))));

    expect($trace['llmProfile']['model'])->toBe('gpt-5-target')
        ->and($stepLines)->toHaveCount(1)
        ->and(is_file($evalDirectory . '/target-messages.json'))->toBeFalse();
});

it('numbers judges/NNN.json and judges/NNN-steps.jsonl by assertion insertion order, not filesystem order, for multiple judged assertions', function (): void {
    $root = freshArtifactRoot();
    $reporter = new ArtifactEvalReporter($root);
    $judge = twoAssertionJudge();
    $config = EvalConfig::default()->withTarget(artifactTarget('sk-not-a-real-secret'))->withJudge($judge)->withReporter($reporter);
    $eval = AgentEval::define('Two judged assertions.', static function (EvalContext $t): void {
        $t->send('go');
        $t->judge()->closedQa('first criterion?')->atLeast(0.0);
        $t->judge()->summarizes('second criterion?')->atLeast(0.0);
    })->withId('cases/two-judged-assertions');

    (new EvalRunner(config: $config))->run(new AgentEvals($eval));

    $judgesDirectory = $reporter->runDirectory() . '/evals/cases/two-judged-assertions/judges';
    $first = json_decode(file_get_contents($judgesDirectory . '/001.json'), true);
    $second = json_decode(file_get_contents($judgesDirectory . '/002.json'), true);

    expect($first['score'])->toBe(0.3)
        ->and($first['reason'])->toBe('first criterion unmet')
        ->and($first['evidence'])->toBe(['first evidence'])
        ->and($second['score'])->toBe(0.9)
        ->and($second['reason'])->toBe('second criterion met')
        ->and(is_file($judgesDirectory . '/001-steps.jsonl'))->toBeTrue()
        ->and(is_file($judgesDirectory . '/002-steps.jsonl'))->toBeTrue();
});

it('never leaks a secret verbatim into any written artifact file', function (): void {
    $root = freshArtifactRoot();
    $secret = 'sk-live-4f9c2b7a1e6d8035bf12a9c4d7e60123';
    $reporter = new ArtifactEvalReporter($root);
    $judge = twoAssertionJudge();
    $config = EvalConfig::default()->withTarget(artifactTarget($secret))->withJudge($judge)->withReporter($reporter);
    $eval = AgentEval::define('Secret must never leak.', static function (EvalContext $t): void {
        $t->send('go');
        $t->succeeded();
        $t->judge()->closedQa('criterion?')->atLeast(0.0);
    })->withId('artifacts/secrecy');

    (new EvalRunner(config: $config))->run(new AgentEvals($eval));

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($reporter->runDirectory(), FilesystemIterator::SKIP_DOTS));
    $checked = 0;
    foreach ($files as $file) {
        if ($file->isFile()) {
            $checked++;
            expect(file_get_contents($file->getPathname()))->not->toContain($secret);
        }
    }
    expect($checked)->toBeGreaterThan(0);
});
