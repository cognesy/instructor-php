<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Polyglot\Inference\Exceptions\ProviderTransientException;
use Cognesy\Tell\Capability\Observation\ExecutionJournal\JournalBackedTellRuns;
use Cognesy\Tell\Data\TellRequest;
use Symfony\Component\Console\Output\BufferedOutput;

it('lists and explains a stopped run from the semantic execution journal', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(
        FakeAgentDriver::fromResponses('answer exceeds limit'),
    ));
    $project = tellLastTemporaryRoot() . '/runs-project';
    mkdir($project, 0700, true);
    tellTestWorkspaces()->initialize($project);
    $application = tellTestApplication($factory);
    $application->setAutoExit(false);
    $output = new BufferedOutput();

    $runStatus = $application->runArgv([
        'tell',
        'trace-secret-prompt',
        '--dir',
        $project,
        '--max-output-chars',
        '4',
        '--output=json',
    ], $output);
    $output->fetch();
    $listStatus = $application->runArgv([
        'tell',
        'runs',
        'list',
        '--status',
        'stopped',
        '--reason',
        'output_limit',
        '--json',
    ], $output);
    $list = json_decode($output->fetch(), true, flags: JSON_THROW_ON_ERROR);
    $executionId = $list['runs'][0]['executionId'] ?? '';

    $showStatus = $application->runArgv([
        'tell',
        'runs',
        'show',
        $executionId,
        '--json',
    ], $output);
    $show = json_decode($output->fetch(), true, flags: JSON_THROW_ON_ERROR);

    expect($runStatus)->toBe(1)
        ->and($listStatus)->toBe(0)
        ->and($list['count'])->toBe(1)
        ->and($list['runs'][0]['status'])->toBe('stopped')
        ->and($list['runs'][0]['reason'])->toBe('output_limit')
        ->and($list['runs'][0]['publication'])->toBe('not_attempted')
        ->and($showStatus)->toBe(0)
        ->and($show['run']['executionId'])->toBe($executionId)
        ->and($show['explanation']['reason'])->toBe('output_limit')
        ->and($show['explanation']['publication']['status'])->toBe('not_attempted')
        ->and($show['trace']['integrity'])->toBe('clean')
        ->and(array_count_values(array_column($show['trace']['events'], 'kind'))['execution.settled'])->toBe(1)
        ->and($show['trace']['events'][array_key_last($show['trace']['events'])]['metadata'])->toMatchArray([
            'reason' => 'output_limit',
            'source' => 'tell:execution_budget',
            'limit' => 4,
            'publication' => 'not_attempted',
        ])
        ->and(json_encode($show, JSON_THROW_ON_ERROR))->not->toContain('trace-secret-prompt');
});

it('reports disabled and failed trace availability from journal facts', function (bool $enabled, string $expected): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(
        FakeAgentDriver::fromResponses('completed'),
    ));
    if (!$enabled) {
        file_put_contents($factory->paths()->configFile, json_encode([
            'schema' => 'tell.config.v1',
            'observability' => ['executionTraces' => false],
        ], JSON_THROW_ON_ERROR));
    }
    if ($expected === 'failed') {
        file_put_contents($factory->paths()->logs, 'blocks trace storage');
    }

    $request = TellRequest::prompt('trace state')->withDirectory(tellLastTemporaryRoot());
    if ($expected === 'disabled') {
        $request = $request->maxOutputChars(4);
    }
    $result = tellTestRuntime($factory)->run($request);
    $show = (new JournalBackedTellRuns($factory->paths()))->show(
        $result->termination()->executionId,
    );

    expect($show)->not->toBeNull()
        ->and($show['trace']['status'])->toBe($expected)
        ->and($show['trace']['events'])->toBe([]);
    if ($expected === 'disabled') {
        expect($show['explanation'])->toMatchArray([
            'reason' => 'output_limit',
            'source' => 'tell:execution_budget',
        ])->and($show['explanation']['context']['limit'])->toBe(4)
            ->and($show['explanation']['context']['outputBytes'])->toBeGreaterThan(4);
    }
})->with([
    'disabled' => [false, 'disabled'],
    'failed' => [true, 'failed'],
]);

it('distinguishes trailing from interior trace corruption without rewriting evidence', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(
        FakeAgentDriver::fromResponses('completed'),
    ));
    $result = tellTestRuntime($factory)->run(
        TellRequest::prompt('corruption test')->withDirectory(tellLastTemporaryRoot()),
    );
    $runs = new JournalBackedTellRuns($factory->paths());
    $path = $result->trace()->path ?? throw new RuntimeException('Expected a written trace.');

    file_put_contents($path, "{malformed\n", FILE_APPEND);
    $trailingHash = hash_file('sha256', $path);
    $trailing = $runs->show($result->termination()->executionId);
    expect($trailing['trace']['integrity'])->toBe('trailing_corruption')
        ->and(hash_file('sha256', $path))->toBe($trailingHash);

    file_put_contents($path, json_encode([
        'executionId' => $result->termination()->executionId,
        'kind' => 'diagnostic.marker',
    ], JSON_THROW_ON_ERROR) . "\n", FILE_APPEND);
    $interiorHash = hash_file('sha256', $path);
    $interior = $runs->show($result->termination()->executionId);
    expect($interior['trace']['integrity'])->toBe('interior_corruption')
        ->and($interior['trace']['malformedLines'])->toHaveCount(1)
        ->and(hash_file('sha256', $path))->toBe($interiorHash);
});

it('retains a safe provider failure identity in journal-backed explanations', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(
        new class implements CanUseTools {
            public function useTools(AgentState $state): AgentState {
                throw new ProviderTransientException('provider-secret-detail');
            }
        },
    ));
    $result = tellTestRuntime($factory)->run(
        TellRequest::prompt('failure-secret-prompt')->withDirectory(tellLastTemporaryRoot()),
    );
    $show = (new JournalBackedTellRuns($factory->paths()))->show(
        $result->termination()->executionId,
    );

    expect($result->status()->value)->toBe('failed')
        ->and($show['explanation']['errors'])->toMatchArray([
            'count' => 1,
            'code' => 'provider_transient_failure',
            'category' => 'provider',
            'phase' => 'inference',
        ])
        ->and($show['trace']['events'][array_key_last($show['trace']['events'])]['metadata']['errorCode'])
        ->toBe('provider_transient_failure')
        ->and(json_encode($show, JSON_THROW_ON_ERROR))->not->toContain('provider-secret-detail')
        ->not->toContain('failure-secret-prompt');
});

it('filters journal runs and isolates executions sharing one session trace', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(
        FakeAgentDriver::fromResponses('completed'),
    ));
    $project = tellLastTemporaryRoot() . '/session-run-project';
    mkdir($project, 0700, true);
    tellTestWorkspaces()->initialize($project);
    $runtime = tellTestRuntime($factory);
    $first = $runtime->run(
        TellRequest::prompt('first')->durable('review')->withDirectory($project),
    );
    $second = $runtime->run(
        TellRequest::prompt('second')->durable('review')->withDirectory($project),
    );
    $runtime->run(
        TellRequest::prompt('branch')->durable()->withDirectory($project),
    );
    $runs = new JournalBackedTellRuns($factory->paths());

    $session = $runs->list([
        'session' => 'review',
        'workspace' => $project,
        'since' => '2000-01-01T00:00:00+00:00',
    ], 50);
    $branch = $runs->list(['branch' => 'main'], 50);
    $limited = $runs->list([], 1);

    expect($session)->toHaveCount(2)
        ->and(array_column($session, 'executionId'))->toContain(
            $first->termination()->executionId,
            $second->termination()->executionId,
        )
        ->and($branch)->toHaveCount(1)
        ->and($limited)->toBe($runs->list([], 1));
    foreach ([$first, $second] as $result) {
        $show = $runs->show($result->termination()->executionId);
        expect(array_unique(array_column($show['trace']['events'], 'executionId')))
            ->toBe([$result->termination()->executionId])
            ->and($show['trace']['events'][0]['sequence'])->toBe(1);
    }
});

it('discovers an abandoned unresolved run from the semantic journal without a terminal trace', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(
        FakeAgentDriver::fromSteps(
            ScenarioStep::toolCall('read_file', ['path' => 'README.md']),
            ScenarioStep::final('unreached'),
        ),
    ));
    $project = tellLastTemporaryRoot() . '/abandoned-run-project';
    mkdir($project, 0700, true);
    file_put_contents($project . '/README.md', "journal evidence\n");
    $run = tellTestRuntime($factory)->start(
        TellRequest::prompt('abandoned-secret-prompt')->withDirectory($project),
    );
    $checkpoints = $run->checkpoints();
    $checkpoints->current();
    $settled = $run->isSettled();
    unset($checkpoints, $run);
    gc_collect_cycles();

    $runs = (new JournalBackedTellRuns($factory->paths()))->list([], 50);
    $abandoned = array_values(array_filter(
        $runs,
        static fn (array $candidate): bool => ($candidate['status'] ?? null) === 'abandoned',
    ));

    expect($settled)->toBeFalse()
        ->and($abandoned)->toHaveCount(1)
        ->and($abandoned[0]['resolved'])->toBeFalse()
        ->and($abandoned[0]['publication'])->toBe('unknown')
        ->and(json_encode($abandoned, JSON_THROW_ON_ERROR))->not->toContain('abandoned-secret-prompt');
});
