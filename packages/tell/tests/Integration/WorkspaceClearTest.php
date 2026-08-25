<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Tell\Canonical\CanonicalConversationRoot;
use Cognesy\Tell\Canonical\CanonicalHash;
use Cognesy\Tell\Canonical\CanonicalLineage;
use Cognesy\Tell\Canonical\CanonicalMessage;
use Cognesy\Tell\Canonical\CanonicalRole;
use Cognesy\Tell\Canonical\CanonicalTextPart;
use Cognesy\Tell\Canonical\CanonicalTurn;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\TellApplication;
use Cognesy\Tell\Workspace\ArenaStore;
use Cognesy\Tell\Workspace\SessionCompatibilityRef;
use Cognesy\Tell\Workspace\TellWorkspace;
use Cognesy\Tell\Workspace\WorkspaceConversationReader;
use Symfony\Component\Console\Output\BufferedOutput;

it('clears a canonical main ref without inference or immutable object deletion', function (): void {
    $factory = tellTestFactory(static function (AgentLoop $loop): AgentLoop {
        throw new RuntimeException('Tell clear must not build an agent loop.');
    });
    $project = tellClearProject($factory);
    $workspace = tellClearWorkspace($factory, $project);
    $arena = new ArenaStore($workspace);
    [$root, $head] = tellClearSeedHistory($arena, 'main');
    $objectsBefore = tellClearSnapshot($workspace->paths->objects);
    $homeBefore = tellClearSnapshot($factory->paths()->home);
    $application = new TellApplication($factory);
    $application->setAutoExit(false);
    $output = new BufferedOutput;

    $status = $application->runArgv(['tell', 'clear', '--dir', $project, '--json'], $output);
    $payload = json_decode($output->fetch(), true, flags: JSON_THROW_ON_ERROR);
    $afterClear = tellClearSnapshot($workspace->paths->arena);

    expect($status)->toBe(0)
        ->and($payload)->toMatchArray([
            'selector' => ['type' => 'main', 'name' => 'main'],
            'previousHead' => $head->toString(),
            'head' => null,
            'empty' => true,
            'changed' => true,
        ])
        ->and($arena->readRef()->head)->toBeNull()
        ->and((new WorkspaceConversationReader($arena))->read()->history()->messages->isEmpty())->toBeTrue()
        ->and($arena->exists($root))->toBeTrue()
        ->and($arena->exists($head))->toBeTrue()
        ->and(tellClearSnapshot($workspace->paths->objects))->toBe($objectsBefore)
        ->and(tellClearSnapshot($factory->paths()->home))->toBe($homeBefore);

    $secondOutput = new BufferedOutput;
    $secondStatus = $application->runArgv(['tell', 'clear', '--dir', $project, '--json'], $secondOutput);
    $secondPayload = json_decode($secondOutput->fetch(), true, flags: JSON_THROW_ON_ERROR);

    expect($secondStatus)->toBe(0)
        ->and($secondPayload)->toMatchArray([
            'previousHead' => null,
            'head' => null,
            'changed' => false,
        ])
        ->and(tellClearSnapshot($workspace->paths->arena))->toBe($afterClear);
});

it('clears only the selected named compatibility ref and leaves legacy state untouched', function (): void {
    $factory = tellTestFactory(static function (AgentLoop $loop): AgentLoop {
        throw new RuntimeException('Tell clear must not build an agent loop.');
    });
    $project = tellClearProject($factory);
    $workspace = tellClearWorkspace($factory, $project);
    $arena = new ArenaStore($workspace);
    [, $mainHead] = tellClearSeedHistory($arena, 'main');
    $session = SessionId::from('review-1');
    $compatibility = new SessionCompatibilityRef($session);
    [, $sessionHead] = tellClearSeedHistory($arena, $compatibility->refName(), $compatibility);
    mkdir($factory->paths()->sessions, 0700, true);
    file_put_contents($factory->paths()->sessions.'/review-1.json', '{"legacy":"unchanged"}');
    $homeBefore = tellClearSnapshot($factory->paths()->home);
    $application = new TellApplication($factory);
    $application->setAutoExit(false);
    $output = new BufferedOutput;

    $status = $application->runArgv(['tell', 'clear', '--dir', $project, '--session', 'review-1', '--json'], $output);
    $display = $output->fetch();
    $payload = json_decode($display, true, flags: JSON_THROW_ON_ERROR);

    expect($status)->toBe(0)
        ->and($payload)->toMatchArray([
            'selector' => ['type' => 'session', 'name' => 'review-1'],
            'previousHead' => $sessionHead->toString(),
            'head' => null,
            'changed' => true,
        ])
        ->and(str_contains($display, $compatibility->refName()))->toBeFalse()
        ->and($arena->readRef()->head?->equals($mainHead))->toBeTrue()
        ->and($arena->readRef($compatibility->refName())->head)->toBeNull()
        ->and(tellClearSnapshot($factory->paths()->home))->toBe($homeBefore);
});

it('fails before mutation for invalid workspace selectors and corrupt canonical history', function (): void {
    $factory = tellTestFactory();
    $project = tellClearProject($factory);
    $workspace = tellClearWorkspace($factory, $project);
    $arena = new ArenaStore($workspace);
    [$root] = tellClearSeedHistory($arena, 'main');
    $application = new TellApplication($factory);
    $application->setAutoExit(false);

    $invalidOutput = new BufferedOutput;
    $invalidStatus = $application->runArgv(['tell', 'clear', '--dir', $project.'/missing', '--json'], $invalidOutput);
    $invalidPayload = json_decode($invalidOutput->fetch(), true, flags: JSON_THROW_ON_ERROR);

    file_put_contents($arena->objectPath($root), '{"kind":"conversation"}');
    $beforeCorrupt = tellClearSnapshot($workspace->paths->arena);
    $corruptOutput = new BufferedOutput;
    $corruptStatus = $application->runArgv(['tell', 'clear', '--dir', $project, '--json'], $corruptOutput);
    $corruptPayload = json_decode($corruptOutput->fetch(), true, flags: JSON_THROW_ON_ERROR);

    expect($invalidStatus)->toBe(2)
        ->and($invalidPayload['error'])->toContain('Workspace directory does not exist')
        ->and($corruptStatus)->toBe(1)
        ->and($corruptPayload['error'])->toContain('object bytes do not match')
        ->and(tellClearSnapshot($workspace->paths->arena))->toBe($beforeCorrupt);
});

function tellClearProject(TellAgentFactory $factory): string
{
    $project = tellLastTemporaryRoot().'/clear-workspace';
    mkdir($project, 0700, true);
    $factory->workspace()->initialize($project);

    return $project;
}

function tellClearWorkspace(TellAgentFactory $factory, string $project): TellWorkspace
{
    $workspace = $factory->workspace()->discover($project);
    if ($workspace === null) {
        throw new RuntimeException('Expected initialized Tell workspace to be discoverable.');
    }

    return $workspace;
}

/** @return array{0: CanonicalHash, 1: CanonicalHash} */
function tellClearSeedHistory(
    ArenaStore $arena,
    string $ref,
    ?SessionCompatibilityRef $compatibility = null,
): array {
    $root = $arena->put(new CanonicalConversationRoot(
        id: 'conversation-clear-'.str_replace('/', '-', $ref),
        messages: [new CanonicalMessage(CanonicalRole::User, [new CanonicalTextPart('retain this history')])],
        session: $compatibility?->metadata(),
    ));
    $head = $arena->put(new CanonicalTurn(
        id: 'turn-clear-'.str_replace('/', '-', $ref),
        lineage: new CanonicalLineage($root),
        messages: [new CanonicalMessage(CanonicalRole::Assistant, [new CanonicalTextPart('clear only the ref')])],
    ));
    $arena->compareAndSwap($ref, null, $head);

    return [$root, $head];
}

/** @return array<string, string> */
function tellClearSnapshot(string $directory): array
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
            throw new RuntimeException("Unable to snapshot Tell clear file: {$file->getPathname()}");
        }
        $files[substr($file->getPathname(), strlen($root))] = $bytes;
    }
    ksort($files);

    return $files;
}
