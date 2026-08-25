<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Agents\AgentLoop;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Runtime\TellPaths;
use Cognesy\Tell\TellApplication;
use Cognesy\Tell\Tests\Support\RecordingDriver;
use Cognesy\Tell\Tests\Support\RequestRecorder;
use Cognesy\Tell\Workspace\ArenaStore;
use Cognesy\Tell\Workspace\TellWorkspace;
use Symfony\Component\Console\Output\BufferedOutput;

it('keeps the complete P0 workspace lifecycle durable across fresh Tell applications', function (): void {
    $recorder = new RequestRecorder;
    $factory = tellTestFactory(static fn (AgentLoop $loop): AgentLoop => $loop->withDriver(
        new RecordingDriver($recorder, 'verified semantic response'),
    ));
    $project = tellLastTemporaryRoot().'/p0-acceptance';
    mkdir($project, 0700, true);

    [$status, $initialized] = tellP0Run(tellP0Application($factory->paths(), $recorder), [
        'init',
        $project,
        '--json',
    ]);
    expect($status)->toBe(0)
        ->and($initialized['status'])->toBe('initialized');

    [$status, $firstTurn] = tellP0Run(tellP0Application($factory->paths(), $recorder), [
        'tell',
        'record the release decision',
        '--dir',
        $project,
        '--output=json',
    ]);
    expect($status)->toBe(0)
        ->and($firstTurn['answer'])->toBe('verified semantic response')
        ->and($firstTurn['execution'])->toBe(['mode' => 'durable', 'durable' => true]);

    [$status, $history] = tellP0Run(tellP0Application($factory->paths(), $recorder), [
        'history',
        '--dir',
        $project,
        '--json',
    ]);
    [$transcriptStatus, $transcript] = tellP0Run(tellP0Application($factory->paths(), $recorder), [
        'transcript',
        '--dir',
        $project,
        '--json',
    ]);
    [$contextStatus, $context] = tellP0Run(tellP0Application($factory->paths(), $recorder), [
        'context',
        '--dir',
        $project,
        '--json',
    ]);
    expect($status)->toBe(0)
        ->and($history['count'])->toBe(1)
        ->and($transcriptStatus)->toBe(0)
        ->and($transcript['messageCount'])->toBe(2)
        ->and($contextStatus)->toBe(0)
        ->and($context['compiled']['messageCount'])->toBe(2);

    $workspace = tellP0Workspace($factory, $project);
    $arenaBeforeTransient = tellP0Snapshot($workspace->paths->arena);
    $sessionsBeforeTransient = tellP0Snapshot($factory->paths()->sessions);
    [$status, $transient] = tellP0Run(tellP0Application($factory->paths(), $recorder), [
        'tell',
        'evaluate an alternative without recording it',
        '--dir',
        $project,
        '--transient',
        '--output=json',
    ]);
    expect($status)->toBe(0)
        ->and($transient['execution'])->toBe(['mode' => 'transient', 'durable' => false])
        ->and(tellP0Snapshot($workspace->paths->arena))->toBe($arenaBeforeTransient)
        ->and(tellP0Snapshot($factory->paths()->sessions))->toBe($sessionsBeforeTransient);

    [$status, $compacted] = tellP0Run(tellP0Application($factory->paths(), $recorder), [
        'compact',
        'retain the release decision',
        '--dir',
        $project,
        '--json',
    ]);
    expect($status)->toBe(0)
        ->and($compacted['changed'])->toBeTrue()
        ->and($compacted['before']['turnCount'])->toBe(1)
        ->and($compacted['after']['turnCount'])->toBe(1);

    expect(tellP0Run(tellP0Application($factory->paths(), $recorder), [
        'tell',
        'continue after compaction',
        '--dir',
        $project,
        '--output=json',
    ])[0])->toBe(0);
    $compactedContinuation = tellP0Messages($recorder->requests[array_key_last($recorder->requests)]);
    expect($compactedContinuation)
        ->toContain(['role' => 'assistant', 'content' => 'verified semantic response'])
        ->toContain(['role' => 'user', 'content' => 'continue after compaction']);

    [$status, $cleared] = tellP0Run(tellP0Application($factory->paths(), $recorder), [
        'clear',
        '--dir',
        $project,
        '--json',
    ]);
    expect($status)->toBe(0)
        ->and($cleared['empty'])->toBeTrue()
        ->and((new ArenaStore($workspace))->readRef()->head)->toBeNull();

    expect(tellP0Run(tellP0Application($factory->paths(), $recorder), [
        'tell',
        'restart after clear',
        '--dir',
        $project,
        '--output=json',
    ])[0])->toBe(0);
    expect(tellP0Run(tellP0Application($factory->paths(), $recorder), [
        'tell',
        'continue after clear',
        '--dir',
        $project,
        '--output=json',
    ])[0])->toBe(0);

    $restartedContinuation = tellP0Messages($recorder->requests[array_key_last($recorder->requests)]);
    expect($restartedContinuation)
        ->toContain(['role' => 'user', 'content' => 'restart after clear'])
        ->toContain(['role' => 'assistant', 'content' => 'verified semantic response'])
        ->toContain(['role' => 'user', 'content' => 'continue after clear'])
        ->not->toContain(['role' => 'user', 'content' => 'record the release decision']);
});

function tellP0Application(TellPaths $paths, RequestRecorder $recorder): TellApplication
{
    $application = new TellApplication(new TellAgentFactory(
        $paths,
        static fn (AgentLoop $loop): AgentLoop => $loop->withDriver(
            new RecordingDriver($recorder, 'verified semantic response'),
        ),
    ));
    $application->setAutoExit(false);

    return $application;
}

/**
 * @param  list<string>  $arguments
 * @return array{0: int, 1: array<string, mixed>}
 */
function tellP0Run(TellApplication $application, array $arguments): array
{
    $output = new BufferedOutput;
    $status = $application->runArgv(['tell', ...$arguments], $output);
    $payload = json_decode($output->fetch(), true, flags: JSON_THROW_ON_ERROR);
    if (! is_array($payload)) {
        throw new RuntimeException('Expected a structured P0 acceptance payload.');
    }

    return [$status, $payload];
}

function tellP0Workspace(TellAgentFactory $factory, string $project): TellWorkspace
{
    $workspace = $factory->workspace()->discover($project);
    if ($workspace === null) {
        throw new RuntimeException('Expected the P0 acceptance workspace.');
    }

    return $workspace;
}

/** @return array<string, string> */
function tellP0Snapshot(string $directory): array
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
            throw new RuntimeException("Unable to snapshot P0 acceptance state: {$file->getPathname()}");
        }
        $files[substr($file->getPathname(), strlen($root))] = $bytes;
    }
    ksort($files);

    return $files;
}

/** @return list<array{role: string, content: string}> */
function tellP0Messages(array $request): array
{
    return array_map(
        static fn (array $message): array => [
            'role' => $message['role'],
            'content' => $message['content'],
        ],
        $request,
    );
}
