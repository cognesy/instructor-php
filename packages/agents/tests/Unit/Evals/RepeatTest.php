<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Evals\Repeat;

use Closure;
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Evals\AgentEval;
use Cognesy\Agents\Evals\AgentEvals;
use Cognesy\Agents\Evals\AgentRun;
use Cognesy\Agents\Evals\ArtifactEvalReporter;
use Cognesy\Agents\Evals\AssertionResult;
use Cognesy\Agents\Evals\AssertionResults;
use Cognesy\Agents\Evals\AssertionSeverity;
use Cognesy\Agents\Evals\ConsoleEvalReporter;
use Cognesy\Agents\Evals\EvalApplication;
use Cognesy\Agents\Evals\EvalConfig;
use Cognesy\Agents\Evals\EvalContext;
use Cognesy\Agents\Evals\EvalExitCode;
use Cognesy\Agents\Evals\EvalRepetition;
use Cognesy\Agents\Evals\EvalResult;
use Cognesy\Agents\Evals\EvalRunner;
use Cognesy\Agents\Evals\EvalRunOptions;
use Cognesy\Agents\Evals\EvalTestFailureMessage;
use Cognesy\Agents\Evals\EvalVerdict;
use Cognesy\Agents\Evals\EvalVerdictResolver;
use Cognesy\Agents\Evals\FakeAgentJudge;
use Cognesy\Agents\Evals\JudgeScore;
use Cognesy\Agents\Evals\JUnitEvalReporter;
use Cognesy\Agents\Evals\LocalAgentTarget;
use Cognesy\Agents\Evals\PestEvalReporter;
use Cognesy\Agents\Evals\PHPUnitEvalReporter;
use Cognesy\Agents\Tests\Support\FrozenClock;
use Cognesy\Agents\Tests\Support\LlmAwareFakeDriver;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use DateTimeImmutable;
use FilesystemIterator;
use InvalidArgumentException;
use PHPUnit\Framework\ExpectationFailedException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Every call to `open()` builds a new loop around a NEW two-response driver, so
 * a session reused across trials is observable: the second `send()` on one
 * session replies 'second reply' and reports two turns, while a fresh session
 * per trial always replies 'first reply' with one turn.
 */
function repeatTarget(?Closure $onOpen = null): LocalAgentTarget {
    return LocalAgentTarget::fromFactory(static function () use ($onOpen): object {
        if ($onOpen !== null) {
            $onOpen();
        }
        $driver = FakeAgentDriver::fromSteps(
            ScenarioStep::final('first reply', new InferenceUsage(100, 42)),
            ScenarioStep::final('second reply', new InferenceUsage(100, 42)),
        );
        return AgentBuilder::base()->withCapability(new UseDriver($driver))->build();
    });
}

/**
 * A judge that returns a scripted score per call, in order - one call per trial
 * for a case with one judged assertion. No inference, no network.
 *
 * @param list<float> $scores
 */
function scriptedJudge(array $scores, bool $withRun = false): FakeAgentJudge {
    $call = 0;
    return FakeAgentJudge::fromClosure(function () use (&$call, $scores, $withRun): JudgeScore {
        $score = $scores[$call] ?? $scores[array_key_last($scores)];
        $call++;
        return new JudgeScore($score, sprintf('trial %d scored %.2f', $call, $score), run: $withRun ? AgentRun::empty() : null);
    });
}

/** A case whose only stochastic element is the judge's score, gated at `$threshold`. */
function judgedEval(string $id, float $threshold = 0.8): AgentEval {
    return AgentEval::define('Refund replies require verification.', static function (EvalContext $t) use ($threshold): void {
        $t->send('Refund order A1049.');
        $t->succeeded();
        $t->judge()->closedQa('Does the reply require verification?')->atLeast($threshold);
    })->withId($id);
}

/** @param list<float> $scores */
function runRepeated(array $scores, int $repeat, float $passRate, string $id = 'repeat/rate', ?EvalConfig $config = null): EvalResult {
    $config ??= EvalConfig::default();
    $config = $config->withTarget(repeatTarget())->withJudge(scriptedJudge($scores));
    $result = (new EvalRunner(config: $config))->run(
        new AgentEvals(judgedEval($id)),
        EvalRunOptions::default()->withRepeat($repeat)->withPassRate($passRate),
    );
    return $result->all()[0];
}

function trialResult(EvalVerdict $verdict, ?float $judgeScore): EvalResult {
    $assertions = $judgeScore === null
        ? AssertionResults::none()
        : new AssertionResults(new AssertionResult(
            name: 'judge:closedQa',
            score: $judgeScore,
            severity: AssertionSeverity::Soft,
            threshold: 0.8,
            judgeScore: new JudgeScore($judgeScore, 'scripted'),
        ));
    return new EvalResult(
        id: 'unit/trial',
        description: 'A hand-built trial.',
        verdict: $verdict,
        assertions: $assertions,
        run: AgentRun::empty(),
        duration: 0.001,
    );
}

function capturedRepeatFailure(Closure $run): ExpectationFailedException {
    try {
        $run();
    } catch (ExpectationFailedException $failure) {
        return $failure;
    }
    throw new RuntimeException('Expected the host test reporter to fail the test.');
}

// k-of-N ARITHMETIC ////////////////////////////////////////////

it('resolves the k-of-N pass threshold', function (int $trials, float $passRate, int $expected): void {
    expect(EvalVerdictResolver::requiredPasses($trials, $passRate))->toBe($expected);
})->with([
    'five trials at 0.8' => [5, 0.8, 4],
    'ten trials at 0.7' => [10, 0.7, 7],
    'three trials at 1.0' => [3, 1.0, 3],
    'one trial at 1.0' => [1, 1.0, 1],
    'ten trials at 0.3' => [10, 0.3, 3],
    'three trials at 0.7 needs all three' => [3, 0.7, 3],
    'four trials at 0.75' => [4, 0.75, 3],
    'nine trials at 0.9 needs all nine' => [9, 0.9, 9],
    'a hundred trials at 0.07' => [100, 0.07, 7],
    'a rate below one trial still needs one pass' => [10, 0.01, 1],
]);

it('does not inherit the off-by-one a bare ceil() produces on a near-integer product', function (): void {
    // On this platform 0.07 * 100 is 7.0000000000000009, so an unguarded
    // (int) ceil() demands 8 of 100 trials when the operator asked for 7% -
    // this is the concrete case the tolerance in requiredPasses() exists for.
    expect((int) ceil(0.07 * 100))->toBe(8)
        ->and(EvalVerdictResolver::requiredPasses(100, 0.07))->toBe(7);
});

// VERDICTS /////////////////////////////////////////////////////

it('passes a case that passes 4 of 5 trials at a 0.8 pass rate', function (): void {
    $result = runRepeated([0.9, 0.9, 0.9, 0.9, 0.4], repeat: 5, passRate: 0.8);

    expect($result->verdict())->toBe(EvalVerdict::Passed)
        ->and($result->passCount())->toBe(4)
        ->and($result->trialCount())->toBe(5)
        ->and($result->repetition()?->requiredPasses())->toBe(4);
});

it('fails a case that passes only 3 of 5 trials at a 0.8 pass rate, even in advisory mode where the shortfall is all soft', function (): void {
    $config = EvalConfig::default()->withTarget(repeatTarget())->withJudge(scriptedJudge([0.9, 0.9, 0.9, 0.4, 0.4]));
    $run = (new EvalRunner(config: $config))->run(
        new AgentEvals(judgedEval('repeat/short')),
        EvalRunOptions::default()->withRepeat(5)->withPassRate(0.8),
    );
    $result = $run->all()[0];

    expect($result->verdict())->toBe(EvalVerdict::Failed)
        ->and($result->passCount())->toBe(3)
        ->and($run->exitCode())->toBe(EvalExitCode::EvalFailure);
});

it('skips a repeated case only when every trial skipped', function (): void {
    $skipping = AgentEval::define('Needs an unconfigured environment.', static function (EvalContext $t): void {
        $t->skip('not configured');
    })->withId('repeat/skipped');
    $config = EvalConfig::default()->withTarget(repeatTarget());

    $result = (new EvalRunner(config: $config))->run(new AgentEvals($skipping), EvalRunOptions::default()->withRepeat(3))->all()[0];

    expect($result->verdict())->toBe(EvalVerdict::Skipped)
        ->and($result->trialCount())->toBe(3);
});

// SPREAD ///////////////////////////////////////////////////////

it('reports the judge-score mean and the POPULATION standard deviation, not the sample one', function (): void {
    $result = runRepeated([0.9, 0.9, 0.9, 0.9, 0.4], repeat: 5, passRate: 0.8);

    // Population: sqrt(0.20 / 5) = 0.2. Sample (N-1) would be sqrt(0.20 / 4) = 0.2236...
    expect($result->judgeScoreMean())->toBe(0.8)
        ->and($result->judgeScoreStdDev())->toBe(0.2)
        ->and($result->judgeScoreStdDev())->not->toBe(round(sqrt(0.2 / 4), 6));
});

it('guards a single judged score against a division by zero and reports nothing judged as absence', function (): void {
    $oneScore = EvalRepetition::fromTrials([
        trialResult(EvalVerdict::Passed, 0.9),
        trialResult(EvalVerdict::Passed, null),
    ], 1.0);
    $noScores = EvalRepetition::fromTrials([
        trialResult(EvalVerdict::Passed, null),
        trialResult(EvalVerdict::Passed, null),
    ], 1.0);

    expect($oneScore->judgeScoreStdDev())->toBe(0.0)
        ->and($oneScore->judgeScoreMean())->toBe(0.9)
        ->and($noScores->judgeScoreStdDev())->toBeNull()
        ->and($noScores->judgeScoreMean())->toBeNull();
});

// FRESH SESSION PER TRIAL //////////////////////////////////////

it('opens a fresh session per trial, so no conversation, assertion or log state leaks between trials', function (): void {
    $opens = 0;
    $config = EvalConfig::default()
        ->withTarget(repeatTarget(static function () use (&$opens): void { $opens++; }))
        ->withJudge(scriptedJudge([0.9]));
    $eval = AgentEval::define('Sends exactly one message per trial.', static function (EvalContext $t): void {
        $t->send('Refund order A1049.');
        $t->succeeded();
        $t->log('trial ran', []);
        $t->judge()->closedQa('Does the reply require verification?')->atLeast(0.8);
    })->withId('repeat/fresh-session');

    $result = (new EvalRunner(config: $config))->run(new AgentEvals($eval), EvalRunOptions::default()->withRepeat(5))->all()[0];
    $trials = $result->trials();

    expect($opens)->toBe(5)
        ->and($trials)->toHaveCount(5);
    foreach ($trials as $index => $trial) {
        // A leaked session would reply 'second reply' with turns=2 from trial 2
        // on; leaked collectors would grow the assertion and log counts per trial.
        expect($trial->run()->reply())->toBe('first reply')
            ->and($trial->run()->turns())->toBe(1)
            ->and($trial->assertions()->count())->toBe(2, "trial {$index} saw another trial's assertions")
            ->and($trial->logs()->all())->toHaveCount(1);
    }
});

// repeat=1 IS THE OLD PATH /////////////////////////////////////

it('leaves a repeat=1 case with no repetition object and no repetition key in its serialized form', function (): void {
    $result = runRepeated([0.9], repeat: 1, passRate: 1.0);

    expect($result->repetition())->toBeNull()
        ->and($result->trials())->toBe([])
        ->and($result->trialCount())->toBe(1)
        ->and($result->judgeScoreMean())->toBeNull()
        ->and(array_key_exists('repetition', $result->toArray()))->toBeFalse();
});

it('produces byte-identical console output for the default options and an explicit repeat=1', function (): void {
    $render = static function (EvalRunOptions $options): string {
        $console = '';
        $config = EvalConfig::default()
            ->withTarget(repeatTarget())
            ->withJudge(scriptedJudge([0.9]))
            ->withReporter(ConsoleEvalReporter::fromWriter(function (string $text) use (&$console): void { $console .= $text; }));
        (new EvalRunner(config: $config))->run(new AgentEvals(judgedEval('repeat/identical')), $options);
        // Durations are the only wall-clock-dependent bytes in this output.
        return preg_replace('/\(\d+\.\d+ms\)/', '(DURATION)', $console) ?? '';
    };

    expect($render(EvalRunOptions::default()->withRepeat(1)))->toBe($render(EvalRunOptions::default()))
        ->and($render(EvalRunOptions::default()))->toContain('[PASSED] repeat/identical');
});

it('treats an explicit repeat=1 and pass-rate=1.0 as the default options', function (): void {
    expect(EvalRunOptions::default()->withRepeat(1)->withPassRate(1.0))->toEqual(EvalRunOptions::default())
        ->and(EvalRunOptions::default()->repeat())->toBe(1)
        ->and(EvalRunOptions::default()->passRate())->toBe(1.0);
});

// OPTIONS AND CLI //////////////////////////////////////////////

it('keeps every other option intact through withRepeat and withPassRate', function (): void {
    $options = EvalRunOptions::default()
        ->withFilter('support/*')
        ->withStrict(true)
        ->withVerbose(true)
        ->withTimeout(2.5)
        ->withRepeat(7)
        ->withPassRate(0.6);

    expect($options->filter())->toBe('support/*')
        ->and($options->strict())->toBeTrue()
        ->and($options->verbose())->toBeTrue()
        ->and($options->timeout())->toBe(2.5)
        ->and($options->repeat())->toBe(7)
        ->and($options->passRate())->toBe(0.6)
        ->and($options->withRepeat(2)->passRate())->toBe(0.6)
        ->and($options->withPassRate(0.5)->repeat())->toBe(7);
});

it('rejects an out-of-range repeat or pass rate rather than coercing it', function (): void {
    expect(fn () => EvalRunOptions::default()->withRepeat(0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => EvalRunOptions::default()->withPassRate(0.0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => EvalRunOptions::default()->withPassRate(1.5))->toThrow(InvalidArgumentException::class)
        ->and(fn () => EvalRunOptions::default()->withPassRate(NAN))->toThrow(InvalidArgumentException::class);
});

it('documents --repeat and --pass-rate in the CLI help', function (): void {
    $help = '';
    $exit = (new EvalApplication())->run(['agents-eval', '--help'], function (string $text) use (&$help): void { $help .= $text; });

    expect($exit)->toBe(EvalExitCode::Success->value)
        ->and($help)->toContain('--repeat=<n>')
        ->and($help)->toContain('--pass-rate=<r>');
});

it('reports a bad --repeat or --pass-rate as a usage error instead of silently coercing it', function (string $argument, string $expected): void {
    $error = '';
    $exit = (new EvalApplication())->run(['agents-eval', $argument], stderr: function (string $text) use (&$error): void { $error .= $text; });

    expect($exit)->toBe(EvalExitCode::ConfigurationError->value)
        ->and($error)->toContain($expected);
})->with([
    'zero trials' => ['--repeat=0', 'Repeat must be a whole number of trials'],
    'a fractional trial count' => ['--repeat=2.5', 'Repeat must be a whole number of trials'],
    'a non-numeric trial count' => ['--repeat=abc', 'Repeat must be a whole number of trials'],
    'a negative trial count' => ['--repeat=-3', 'Repeat must be a whole number of trials'],
    'a zero pass rate' => ['--pass-rate=0', 'Pass rate must be greater than 0 and at most 1'],
    'a pass rate above one' => ['--pass-rate=1.5', 'Pass rate must be greater than 0 and at most 1'],
    'a non-numeric pass rate' => ['--pass-rate=most', 'Pass rate must be a number greater than 0 and at most 1'],
]);

it('runs the requested number of trials through the CLI', function (): void {
    $root = sys_get_temp_dir() . '/eval-repeat-cli-' . bin2hex(random_bytes(4));
    mkdir($root, 0777, true);
    file_put_contents($root . '/smoke.eval.php', "<?php return \\Cognesy\\Agents\\Evals\\AgentEval::define('smoke', static function(\\Cognesy\\Agents\\Evals\\EvalContext \$t): void { \$t->send('x'); \$t->succeeded(); });");
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
    $output = '';

    $exit = (new EvalApplication())->run(
        ['agents-eval', $root, '--repeat=3', '--pass-rate=0.5', '--json', '--skip-report'],
        function (string $text) use (&$output): void { $output .= $text; },
    );
    $decoded = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

    expect($exit)->toBe(EvalExitCode::Success->value)
        ->and($decoded['results'][0]['repetition']['trials'])->toBe(3)
        ->and($decoded['results'][0]['repetition']['passed'])->toBe(3)
        ->and($decoded['results'][0]['repetition']['required'])->toBe(2);
    unlink($root . '/evals.config.php');
    unlink($root . '/smoke.eval.php');
    rmdir($root);
});

// REPORTING ////////////////////////////////////////////////////

it('renders the rate and the judge spread on the console when N > 1', function (): void {
    $console = '';
    $config = EvalConfig::default()
        ->withTarget(repeatTarget())
        ->withJudge(scriptedJudge([0.9, 0.9, 0.9, 0.9, 0.4]))
        ->withReporter(ConsoleEvalReporter::fromWriter(function (string $text) use (&$console): void { $console .= $text; }));

    (new EvalRunner(config: $config))->run(
        new AgentEvals(judgedEval('refund-requires-verification')),
        EvalRunOptions::default()->withRepeat(5)->withPassRate(0.8),
    );

    expect($console)->toContain('PASS 4/5  refund-requires-verification  judge=0.80+/-0.20')
        ->and($console)->not->toContain('[PASSED] refund-requires-verification');
});

it('lists each trial on the console in verbose mode', function (): void {
    $console = '';
    $config = EvalConfig::default()
        ->withTarget(repeatTarget())
        ->withJudge(scriptedJudge([0.9, 0.4, 0.9]))
        ->withReporter(ConsoleEvalReporter::fromWriter(function (string $text) use (&$console): void { $console .= $text; }));

    (new EvalRunner(config: $config))->run(
        new AgentEvals(judgedEval('repeat/verbose')),
        EvalRunOptions::default()->withRepeat(3)->withPassRate(0.6)->withVerbose(true),
    );

    expect($console)->toContain('TRIAL 1/3 passed judge=0.90')
        ->and($console)->toContain('TRIAL 2/3 scored judge=0.40')
        ->and($console)->toContain('TRIAL 3/3 passed judge=0.90');
});

it('omits the judge field from the rate line when nothing in the case was judged', function (): void {
    $console = '';
    $config = EvalConfig::default()
        ->withTarget(repeatTarget())
        ->withReporter(ConsoleEvalReporter::fromWriter(function (string $text) use (&$console): void { $console .= $text; }));
    $eval = AgentEval::define('No judge involved.', static function (EvalContext $t): void {
        $t->send('x');
        $t->succeeded();
    })->withId('repeat/unjudged');

    (new EvalRunner(config: $config))->run(new AgentEvals($eval), EvalRunOptions::default()->withRepeat(2));
    $rateLine = array_values(array_filter(explode("\n", $console), static fn (string $line): bool => str_contains($line, 'PASS 2/2')));

    expect($rateLine)->toHaveCount(1)
        ->and($rateLine[0])->toContain('PASS 2/2  repeat/unjudged')
        ->and($rateLine[0])->not->toContain('judge=');
});

it('translates a missed pass rate into a comprehensible PHPUnit failure', function (): void {
    $config = EvalConfig::default()
        ->withTarget(repeatTarget())
        ->withJudge(scriptedJudge([0.9, 0.9, 0.9, 0.4, 0.4]))
        ->withReporter(PHPUnitEvalReporter::default());

    $failure = capturedRepeatFailure(static fn () => (new EvalRunner(config: $config))->run(
        new AgentEvals(judgedEval('repeat/phpunit')),
        EvalRunOptions::default()->withRepeat(5)->withPassRate(0.8),
    ));

    expect($failure->getMessage())
        ->toContain('[failed] repeat/phpunit')
        ->toContain('- repetition: passed 3/5, needed 4/5')
        ->toContain('judge mean 0.70');
});

it('translates a missed pass rate into the same comprehensible Pest failure', function (): void {
    $config = EvalConfig::default()
        ->withTarget(repeatTarget())
        ->withJudge(scriptedJudge([0.9, 0.9, 0.9, 0.4, 0.4]))
        ->withReporter(PestEvalReporter::default());

    $failure = capturedRepeatFailure(static fn () => (new EvalRunner(config: $config))->run(
        new AgentEvals(judgedEval('repeat/pest')),
        EvalRunOptions::default()->withRepeat(5)->withPassRate(0.8),
    ));

    expect($failure->getMessage())->toContain('- repetition: passed 3/5, needed 4/5');
});

it('keeps the failure message free of repetition wording for a case that ran once', function (): void {
    $config = EvalConfig::default()->withTarget(repeatTarget())->withJudge(scriptedJudge([0.4]));
    $run = (new EvalRunner(config: $config))->run(
        new AgentEvals(judgedEval('repeat/single')),
        EvalRunOptions::default()->withStrict(true),
    );

    expect(EvalTestFailureMessage::fromResult($run))->not->toContain('repetition');
});

it('names the missed pass rate in JUnit XML instead of a bare gate failure', function (): void {
    $path = sys_get_temp_dir() . '/eval-repeat-junit-' . bin2hex(random_bytes(4)) . '/junit.xml';
    $config = EvalConfig::default()
        ->withTarget(repeatTarget())
        ->withJudge(scriptedJudge([0.9, 0.9, 0.9, 0.4, 0.4]))
        ->withReporter(new JUnitEvalReporter($path));

    (new EvalRunner(config: $config))->run(
        new AgentEvals(judgedEval('repeat/junit')),
        EvalRunOptions::default()->withRepeat(5)->withPassRate(0.8),
    );
    $xml = (string) file_get_contents($path);

    expect($xml)->toContain('passed 3/5 trials, needed 4/5')
        ->and($xml)->not->toContain('gate failed');
    unlink($path);
    rmdir(dirname($path));
});

// ARTIFACTS ////////////////////////////////////////////////////

it('records the real repeat count in the provenance block of details.json and summary.json', function (): void {
    $root = sys_get_temp_dir() . '/eval-repeat-artifacts-' . bin2hex(random_bytes(4));
    $reporter = new ArtifactEvalReporter(
        root: $root,
        clock: new FrozenClock(new DateTimeImmutable('2026-01-15T10:00:00+00:00')),
        gitShaResolver: static fn (): string => 'deadbeef',
        packageVersionResolver: static fn (): string => '9.9.9-test',
    );
    $config = EvalConfig::default()->withTarget(repeatTarget())->withJudge(scriptedJudge([0.9, 0.9, 0.4]))->withReporter($reporter);

    (new EvalRunner(config: $config))->run(
        new AgentEvals(judgedEval('repeat/provenance')),
        EvalRunOptions::default()->withRepeat(3)->withPassRate(0.6),
    );
    $details = json_decode((string) file_get_contents($reporter->runDirectory() . '/evals/repeat/provenance/details.json'), true, flags: JSON_THROW_ON_ERROR);
    $summary = json_decode((string) file_get_contents($reporter->runDirectory() . '/summary.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($details['provenance']['repeat'])->toBe(3)
        ->and($summary['provenance']['repeat'])->toBe(3)
        ->and($details['provenance']['startedAt'])->toBe('2026-01-15T10:00:00+00:00')
        ->and($details['provenance']['package'])->toBe(['version' => '9.9.9-test', 'gitSha' => 'deadbeef']);
});

it('retains every trial\'s assertions in artifacts, stably numbered in execution order', function (): void {
    $root = sys_get_temp_dir() . '/eval-repeat-trials-' . bin2hex(random_bytes(4));
    $reporter = new ArtifactEvalReporter($root);
    $config = EvalConfig::default()
        ->withTarget(repeatTarget())
        ->withJudge(scriptedJudge([0.9, 0.4, 0.9], withRun: true))
        ->withReporter($reporter);

    (new EvalRunner(config: $config))->run(
        new AgentEvals(judgedEval('repeat/trial-artifacts')),
        EvalRunOptions::default()->withRepeat(3)->withPassRate(0.6),
    );
    $directory = $reporter->runDirectory() . '/evals/repeat/trial-artifacts';
    $details = json_decode((string) file_get_contents($directory . '/details.json'), true, flags: JSON_THROW_ON_ERROR);
    $trialScores = array_map(
        static fn (array $trial): float => $trial['assertions'][1]['judge']['score'],
        $details['repetition']['results'],
    );
    $secondTrial = json_decode((string) file_get_contents($directory . '/trials/002/details.json'), true, flags: JSON_THROW_ON_ERROR);
    $secondTrialJudge = json_decode((string) file_get_contents($directory . '/trials/002/judges/001.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($details['repetition']['results'])->toHaveCount(3)
        ->and(array_column($details['repetition']['results'], 'trial'))->toBe([1, 2, 3])
        ->and($trialScores)->toBe([0.9, 0.4, 0.9])
        ->and($details['repetition']['satisfied'])->toBeTrue()
        ->and($secondTrial['verdict'])->toBe('scored')
        ->and($secondTrial['assertions'][1]['judge']['score'])->toBe(0.4)
        ->and($secondTrialJudge['score'])->toBe(0.4)
        ->and(is_file($directory . '/trials/002/target-steps.jsonl'))->toBeTrue()
        ->and(is_dir($directory . '/trials/004'))->toBeFalse();
});

it('never leaks a secret verbatim into the new per-trial artifact files', function (): void {
    $root = sys_get_temp_dir() . '/eval-repeat-secrecy-' . bin2hex(random_bytes(4));
    $secret = 'sk-live-4f9c2b7a1e6d8035bf12a9c4d7e60123';
    $reporter = new ArtifactEvalReporter($root);
    // The secret lives only in LLMConfig::$apiKey, so this isolates the claim
    // under test: the per-trial files added here serialize through the same
    // digesting path as every other artifact, and gain no new leak channel.
    $llm = new LLMConfig(model: 'gpt-5-target', driver: 'openai', apiKey: $secret);
    $target = LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()->withCapability(new UseDriver(
        new LlmAwareFakeDriver(FakeAgentDriver::fromSteps(ScenarioStep::final('ok', new InferenceUsage(20, 8))), $llm),
    ))->build());
    $config = EvalConfig::default()->withTarget($target)->withJudge(scriptedJudge([0.9, 0.4], withRun: true))->withReporter($reporter);

    (new EvalRunner(config: $config))->run(
        new AgentEvals(judgedEval('repeat/secrecy')),
        EvalRunOptions::default()->withRepeat(2)->withPassRate(0.5),
    );
    $trialFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
        $reporter->runDirectory() . '/evals/repeat/secrecy/trials',
        FilesystemIterator::SKIP_DOTS,
    ));
    $checked = 0;
    foreach ($trialFiles as $file) {
        if ($file->isFile()) {
            $checked++;
            expect(file_get_contents($file->getPathname()))->not->toContain($secret);
        }
    }
    expect($checked)->toBeGreaterThan(0);
});

it('writes no per-trial artifact directory for a case that ran once', function (): void {
    $root = sys_get_temp_dir() . '/eval-repeat-single-' . bin2hex(random_bytes(4));
    $reporter = new ArtifactEvalReporter($root);
    $config = EvalConfig::default()->withTarget(repeatTarget())->withJudge(scriptedJudge([0.9]))->withReporter($reporter);

    (new EvalRunner(config: $config))->run(new AgentEvals(judgedEval('repeat/once')));

    expect(is_dir($reporter->runDirectory() . '/evals/repeat/once/trials'))->toBeFalse();
});

// COST /////////////////////////////////////////////////////////

it('sums token cost across trials rather than reporting one trial as the whole case', function (): void {
    $single = runRepeated([0.9], repeat: 1, passRate: 1.0, id: 'repeat/cost-single');
    $repeated = runRepeated([0.9, 0.9, 0.9], repeat: 3, passRate: 1.0, id: 'repeat/cost-repeated');

    expect($single->tokens()['target'])->toBeGreaterThan(0)
        ->and($repeated->tokens()['target'])->toBe($single->tokens()['target'] * 3);
});
