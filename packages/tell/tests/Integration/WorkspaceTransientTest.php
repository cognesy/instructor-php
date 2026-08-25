<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Continuation\AgentStopException;
use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Tell\Canonical\CanonicalConversationRoot;
use Cognesy\Tell\Canonical\CanonicalLineage;
use Cognesy\Tell\Canonical\CanonicalMessage;
use Cognesy\Tell\Canonical\CanonicalRole;
use Cognesy\Tell\Canonical\CanonicalTextPart;
use Cognesy\Tell\Canonical\CanonicalTurn;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\TellCommand;
use Cognesy\Tell\Tests\Support\RecordingDriver;
use Cognesy\Tell\Tests\Support\RequestRecorder;
use Cognesy\Tell\Workspace\ArenaStore;
use Cognesy\Tell\Workspace\SessionCompatibilityRef;
use Cognesy\Tell\Workspace\TellWorkspace;
use Symfony\Component\Console\Tester\CommandTester;

it('runs transient and durable turns with the same compiled workspace context while only the durable turn publishes', function (): void {
    $recorder = new RequestRecorder;
    $factory = tellTestFactory(static fn (AgentLoop $loop): AgentLoop => $loop->withDriver(new RecordingDriver($recorder, 'answer')));
    $project = tellTransientProject($factory);
    $workspace = tellTransientWorkspace($factory, $project);
    tellTransientSeedHistory(new ArenaStore($workspace));
    $beforeArena = tellTransientSnapshot($workspace->paths->arena);
    $beforeSessions = tellTransientSnapshot($factory->paths()->sessions);
    $command = new TellCommand($factory);
    $transient = new CommandTester($command);

    expect($transient->execute([
        'prompt' => 'next action',
        '--dir' => $project,
        '--transient' => true,
        '--output' => 'json',
    ]))->toBe(0);
    $transientPayload = json_decode($transient->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($transientPayload)->toMatchArray([
        'answer' => 'answer',
        'execution' => ['mode' => 'transient', 'durable' => false],
    ])
        ->and(tellTransientSnapshot($workspace->paths->arena))->toBe($beforeArena)
        ->and(tellTransientSnapshot($factory->paths()->sessions))->toBe($beforeSessions);

    $durable = new CommandTester($command);
    expect($durable->execute([
        'prompt' => 'next action',
        '--dir' => $project,
        '--output' => 'json',
    ]))->toBe(0);

    $messages = static fn (array $request): array => array_map(
        static fn (array $message): array => [
            'role' => $message['role'],
            'content' => $message['content'],
        ],
        $request,
    );

    expect($recorder->requests)->toHaveCount(2)
        ->and($messages($recorder->requests[0]))->toBe($messages($recorder->requests[1]));
});

it('reads a named compatible workspace history without changing its arena or legacy source', function (): void {
    $recorder = new RequestRecorder;
    $factory = tellTestFactory(static fn (AgentLoop $loop): AgentLoop => $loop->withDriver(new RecordingDriver($recorder, 'session answer')));
    $project = tellTransientProject($factory);
    $workspace = tellTransientWorkspace($factory, $project);
    $session = SessionId::from('review-1');
    $compatibility = new SessionCompatibilityRef($session);
    tellTransientSeedHistory(new ArenaStore($workspace), $compatibility->refName(), $compatibility);
    mkdir($factory->paths()->sessions, 0700, true);
    file_put_contents($factory->paths()->sessions.'/review-1.json', '{"legacy":"unchanged"}');
    $beforeArena = tellTransientSnapshot($workspace->paths->arena);
    $beforeSessions = tellTransientSnapshot($factory->paths()->sessions);
    $tester = new CommandTester(new TellCommand($factory));

    expect($tester->execute([
        'prompt' => 'inspect session safely',
        '--dir' => $project,
        '--session' => 'review-1',
        '--transient' => true,
        '--output' => 'json',
    ]))->toBe(0);

    $messages = array_map(
        static fn (array $message): array => [
            'role' => $message['role'],
            'content' => $message['content'],
        ],
        $recorder->requests[0],
    );

    expect($messages)->toContain([
        'role' => 'user',
        'content' => 'initial transient constraints',
    ])
        ->and(tellTransientSnapshot($workspace->paths->arena))->toBe($beforeArena)
        ->and(tellTransientSnapshot($factory->paths()->sessions))->toBe($beforeSessions);
});

it('keeps transient text and events explicitly non-durable while leaving tool-enabled workspace state unchanged', function (): void {
    $factory = tellTestFactory(static fn (AgentLoop $loop): AgentLoop => $loop->withDriver(FakeAgentDriver::fromSteps(
        ScenarioStep::toolCall('read', ['path' => 'notes.txt']),
        ScenarioStep::final('tool-assisted transient answer'),
    )));
    $project = tellTransientProject($factory);
    $workspace = tellTransientWorkspace($factory, $project);
    file_put_contents($project.'/notes.txt', 'safe workspace tool input');
    tellTransientSeedHistory(new ArenaStore($workspace));
    $before = tellTransientSnapshot($workspace->paths->arena);
    $text = new CommandTester(new TellCommand($factory));

    expect($text->execute([
        'prompt' => 'read the note transiently',
        '--dir' => $project,
        '--transient' => true,
        '--output' => 'text',
    ], ['capture_stderr_separately' => true]))->toBe(0)
        ->and(trim($text->getDisplay()))->toBe('tool-assisted transient answer')
        ->and($text->getErrorOutput())->toContain('transient: no conversation or session state was persisted')
        ->and(tellTransientSnapshot($workspace->paths->arena))->toBe($before);

    $events = new CommandTester(new TellCommand($factory));
    expect($events->execute([
        'prompt' => 'repeat safely',
        '--dir' => $project,
        '--transient' => true,
        '--output' => 'events',
    ]))->toBe(0);
    $lines = array_map(
        static fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
        array_values(array_filter(explode("\n", trim($events->getDisplay())))),
    );

    expect($lines)->toContain(['event' => 'TellTransientExecution', 'data' => ['durable' => false]])
        ->and(tellTransientSnapshot($workspace->paths->arena))->toBe($before);
});

it('marks transient failures and cancellation without publishing state', function (CanUseTools $driver, int $expectedStatus): void {
    $factory = tellTestFactory(static fn (AgentLoop $loop): AgentLoop => $loop->withDriver($driver));
    $project = tellTransientProject($factory);
    $workspace = tellTransientWorkspace($factory, $project);
    tellTransientSeedHistory(new ArenaStore($workspace));
    $beforeArena = tellTransientSnapshot($workspace->paths->arena);
    $beforeSessions = tellTransientSnapshot($factory->paths()->sessions);
    $tester = new CommandTester(new TellCommand($factory));

    $status = $tester->execute([
        'prompt' => 'must not persist',
        '--dir' => $project,
        '--transient' => true,
        '--output' => 'json',
    ]);
    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($status)->toBe($expectedStatus)
        ->and($payload['execution'])->toBe(['mode' => 'transient', 'durable' => false])
        ->and(tellTransientSnapshot($workspace->paths->arena))->toBe($beforeArena)
        ->and(tellTransientSnapshot($factory->paths()->sessions))->toBe($beforeSessions);
})->with([
    'driver failure' => [new FakeAgentDriver([ScenarioStep::error('failed')]), 1],
    'cancellation' => [new class implements CanUseTools
    {
        public function useTools(AgentState $state): AgentState
        {
            throw new AgentStopException(StopSignal::userRequested('cancelled'));
        }
    }, 1],
]);

it('keeps a no-workspace transient invocation stateless and records only redacted transient traces', function (): void {
    $factory = tellTestFactory(static fn (AgentLoop $loop): AgentLoop => $loop->withDriver(FakeAgentDriver::fromResponses('stateless answer')));
    $project = tellLastTemporaryRoot().'/no-workspace';
    mkdir($project, 0700, true);
    $tester = new CommandTester(new TellCommand($factory));

    expect($tester->execute([
        'prompt' => 'transient private prompt',
        '--dir' => $project,
        '--session' => 'ignored-for-storage',
        '--transient' => true,
        '--output' => 'json',
    ]))->toBe(0);
    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
    $files = tellTransientTraceFiles($factory->paths()->executionTraces);
    $sessionFiles = glob($factory->paths()->sessionTraces.'/*.jsonl') ?: [];
    $records = tellTransientTraceRecords($sessionFiles[0] ?? '');

    expect($payload['execution'])->toBe(['mode' => 'transient', 'durable' => false])
        ->and(is_dir($project.'/.tell'))->toBeFalse()
        ->and(is_dir($factory->paths()->sessions))->toBeFalse()
        ->and($files)->toBe([])
        ->and($sessionFiles)->toHaveCount(1)
        ->and($records[0]['transient'])->toBeTrue()
        ->and($records[0]['data']['messagePayload'])->toBe('[omitted]')
        ->and(json_encode($records, JSON_THROW_ON_ERROR))->not->toContain('transient private prompt');
});

function tellTransientProject(TellAgentFactory $factory): string
{
    $project = tellLastTemporaryRoot().'/transient-workspace';
    mkdir($project, 0700, true);
    $factory->workspace()->initialize($project);

    return $project;
}

function tellTransientWorkspace(TellAgentFactory $factory, string $project): TellWorkspace
{
    $workspace = $factory->workspace()->discover($project);
    if ($workspace === null) {
        throw new RuntimeException('Expected initialized Tell workspace to be discoverable.');
    }

    return $workspace;
}

function tellTransientSeedHistory(
    ArenaStore $arena,
    string $ref = 'main',
    ?SessionCompatibilityRef $compatibility = null,
): void {
    $suffix = str_replace('/', '-', $ref);
    $root = $arena->put(new CanonicalConversationRoot(
        id: 'conversation-transient-'.$suffix,
        messages: [new CanonicalMessage(CanonicalRole::User, [new CanonicalTextPart('initial transient constraints')])],
        session: $compatibility?->metadata(),
    ));
    $head = $arena->put(new CanonicalTurn(
        id: 'turn-transient-'.$suffix,
        lineage: new CanonicalLineage($root),
        messages: [new CanonicalMessage(CanonicalRole::Assistant, [new CanonicalTextPart('durable prior answer')])],
    ));
    $arena->compareAndSwap($ref, null, $head);
}

/** @return array<string, string> */
function tellTransientSnapshot(string $directory): array
{
    $files = [];
    if (! is_dir($directory)) {
        return $files;
    }
    $root = rtrim($directory, '/\\').DIRECTORY_SEPARATOR;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }
        $bytes = file_get_contents($file->getPathname());
        if ($bytes === false) {
            throw new RuntimeException("Unable to snapshot transient state: {$file->getPathname()}");
        }
        $files[substr($file->getPathname(), strlen($root))] = $bytes;
    }
    ksort($files);

    return $files;
}

/** @return list<string> */
function tellTransientTraceFiles(string $directory): array
{
    $files = glob($directory.'/*/*.jsonl') ?: [];
    sort($files);

    return array_values($files);
}

/** @return list<array<string, mixed>> */
function tellTransientTraceRecords(string $path): array
{
    $contents = file_get_contents($path);
    if (! is_string($contents)) {
        return [];
    }

    return array_map(
        static fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
        array_values(array_filter(explode("\n", $contents))),
    );
}
