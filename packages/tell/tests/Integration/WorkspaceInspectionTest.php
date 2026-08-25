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
use Cognesy\Tell\Canonical\CanonicalToolCall;
use Cognesy\Tell\Canonical\CanonicalToolResult;
use Cognesy\Tell\Canonical\CanonicalTurn;
use Cognesy\Tell\Command\WorkspaceInspectionCommand;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\TellApplication;
use Cognesy\Tell\Workspace\ArenaStore;
use Cognesy\Tell\Workspace\SessionCompatibilityRef;
use Cognesy\Tell\Workspace\TellWorkspace;
use HelgeSverre\Toon\Toon;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

it('inspects an empty workspace without inference or persistence writes', function (): void {
    $factory = tellTestFactory(static function (AgentLoop $loop): AgentLoop {
        throw new RuntimeException('Inspection must not build an agent loop.');
    });
    $project = tellInspectionProject($factory);
    $workspace = tellInspectionWorkspace($factory, $project);
    $before = tellInspectionArenaSnapshot($workspace);
    $homeBefore = tellInspectionDirectorySnapshot($factory->paths()->home);
    $application = new TellApplication($factory);
    $application->setAutoExit(false);
    $output = new BufferedOutput;

    $status = $application->runArgv(['tell', 'history', '--dir', $project, '--json'], $output);
    $payload = json_decode($output->fetch(), true, flags: JSON_THROW_ON_ERROR);
    $toonOutput = new BufferedOutput;
    $toonStatus = $application->runArgv(['tell', 'transcript', '--dir', $project], $toonOutput);
    $toon = Toon::decode($toonOutput->fetch());

    expect($status)->toBe(0)
        ->and($payload)->toMatchArray([
            'selector' => ['type' => 'main', 'name' => 'main'],
            'head' => null,
            'root' => null,
            'order' => 'oldest-first',
            'totalCount' => 0,
            'count' => 0,
            'turns' => [],
        ])
        ->and($payload['message'])->toContain('No canonical turns')
        ->and($toonStatus)->toBe(0)
        ->and($toon)->toMatchArray([
            'selector' => ['type' => 'main', 'name' => 'main'],
            'messageCount' => 0,
            'messages' => [],
        ])
        ->and(tellInspectionArenaSnapshot($workspace))->toBe($before)
        ->and(tellInspectionDirectorySnapshot($factory->paths()->home))->toBe($homeBefore);
});

it('lists canonical turns oldest-first with bounded Unicode previews and explicit full detail', function (): void {
    $factory = tellTestFactory();
    $project = tellInspectionProject($factory);
    $arena = new ArenaStore(tellInspectionWorkspace($factory, $project));
    [, $first, $second, $longAnswer] = tellInspectionSeedHistory($arena);
    $before = tellInspectionArenaSnapshot(tellInspectionWorkspace($factory, $project));
    $command = new WorkspaceInspectionCommand('history', $factory);

    $bounded = new CommandTester($command);
    expect($bounded->execute(['--dir' => $project, '--limit' => '1', '--json' => true]))->toBe(0);
    $boundedPayload = json_decode($bounded->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    $full = new CommandTester($command);
    expect($full->execute(['--dir' => $project, '--limit' => '2', '--full' => true, '--json' => true]))->toBe(0);
    $fullPayload = json_decode($full->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($boundedPayload)->toMatchArray([
        'order' => 'oldest-first',
        'totalCount' => 2,
        'count' => 1,
        'truncated' => true,
    ])
        ->and($boundedPayload['turns'][0])->toMatchArray([
            'head' => $second->toString(),
            'id' => 'turn-inspection-two',
            'parent' => $first->toString(),
            'status' => 'completed',
            'messageCount' => 2,
        ])
        ->and($boundedPayload['turns'][0]['truncated'])->toBeTrue()
        ->and($boundedPayload['turns'][0]['content'])->toEndWith('...')
        ->and($boundedPayload['help'][0])->toContain('--limit 2')
        ->and(array_column($fullPayload['turns'], 'id'))->toBe([
            'turn-inspection-one',
            'turn-inspection-two',
        ])
        ->and($fullPayload['turns'][1]['content'])->toContain($longAnswer)
        ->and($fullPayload['turns'][1]['truncated'])->toBeFalse()
        ->and(tellInspectionArenaSnapshot(tellInspectionWorkspace($factory, $project)))->toBe($before);
});

it('renders verified ordered tool call and result pairs without exposing provider data', function (): void {
    $factory = tellTestFactory();
    $project = tellInspectionProject($factory);
    $arena = new ArenaStore(tellInspectionWorkspace($factory, $project));
    $root = $arena->put(new CanonicalConversationRoot(
        id: 'conversation-tool-inspection',
        messages: [new CanonicalMessage(CanonicalRole::User, [new CanonicalTextPart('calculate safely')])],
    ));
    $turn = $arena->put(new CanonicalTurn(
        id: 'turn-tool-inspection',
        lineage: new CanonicalLineage($root),
        messages: [new CanonicalMessage(CanonicalRole::Assistant, [new CanonicalTextPart('The answer is 4.')])],
        toolCalls: [new CanonicalToolCall('tool-call-inspection', 'calculator', ['expression' => '2 + 2'])],
        toolResults: [new CanonicalToolResult('tool-call-inspection', [new CanonicalTextPart('4')])],
    ));
    $arena->compareAndSwap('main', null, $turn);
    $workspace = tellInspectionWorkspace($factory, $project);
    $before = tellInspectionArenaSnapshot($workspace);
    $tester = new CommandTester(new WorkspaceInspectionCommand('transcript', $factory));

    expect($tester->execute(['--dir' => $project, '--full' => true, '--json' => true]))->toBe(0);
    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload)->toMatchArray([
        'messageCount' => 4,
        'toolCallCount' => 1,
        'toolResultCount' => 1,
    ])
        ->and(array_column($payload['messages'], 'role'))->toBe(['user', 'assistant', 'tool', 'assistant'])
        ->and($payload['messages'][1]['toolCalls'])->toBe([[
            'id' => 'tool-call-inspection',
            'name' => 'calculator',
            'arguments' => '{"expression":"2 + 2"}',
            'argumentCharacters' => 22,
            'argumentsTruncated' => false,
        ]])
        ->and($payload['messages'][2]['toolResult'])->toMatchArray([
            'callId' => 'tool-call-inspection',
            'name' => 'calculator',
            'isError' => false,
            'content' => '4',
        ])
        ->and(implode('', tellInspectionArenaSnapshot($workspace)))->not->toContain('provider-wire')
        ->and(tellInspectionArenaSnapshot($workspace))->toBe($before);
});

it('selects named canonical sessions without exposing their hashed compatibility ref', function (): void {
    $factory = tellTestFactory();
    $project = tellInspectionProject($factory);
    $arena = new ArenaStore(tellInspectionWorkspace($factory, $project));
    $compatibility = new SessionCompatibilityRef(SessionId::from('review-1'));
    $root = $arena->put(new CanonicalConversationRoot(
        id: 'conversation-inspection-session',
        messages: [new CanonicalMessage(CanonicalRole::User, [new CanonicalTextPart('session history')])],
        session: $compatibility->metadata(),
    ));
    $arena->compareAndSwap($compatibility->refName(), null, $root);
    $before = tellInspectionArenaSnapshot(tellInspectionWorkspace($factory, $project));
    $tester = new CommandTester(new WorkspaceInspectionCommand('transcript', $factory));

    expect($tester->execute(['--dir' => $project, '--session' => 'review-1', '--json' => true]))->toBe(0);
    $display = $tester->getDisplay();
    $payload = json_decode($display, true, flags: JSON_THROW_ON_ERROR);

    expect($payload['selector'])->toBe(['type' => 'session', 'name' => 'review-1'])
        ->and($payload['messages'][0]['content'])->toBe('session history')
        ->and(str_contains($display, $compatibility->refName()))->toBeFalse()
        ->and(tellInspectionArenaSnapshot(tellInspectionWorkspace($factory, $project)))->toBe($before);
});

it('fails atomically for corrupt lineage and malformed limits', function (): void {
    $factory = tellTestFactory();
    $project = tellInspectionProject($factory);
    $workspace = tellInspectionWorkspace($factory, $project);
    $arena = new ArenaStore($workspace);
    [$root] = tellInspectionSeedHistory($arena);
    $command = new WorkspaceInspectionCommand('history', $factory);

    $beforeInvalid = tellInspectionArenaSnapshot($workspace);
    $invalid = new CommandTester($command);
    expect($invalid->execute(['--dir' => $project, '--limit' => '0', '--json' => true]))->toBe(2);
    $invalidPayload = json_decode($invalid->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
    expect(tellInspectionArenaSnapshot($workspace))->toBe($beforeInvalid);

    file_put_contents($arena->objectPath($root), '{"kind":"conversation"}');
    $beforeCorrupt = tellInspectionArenaSnapshot($workspace);
    $corrupt = new CommandTester($command);
    expect($corrupt->execute(['--dir' => $project, '--json' => true]))->toBe(1);
    $corruptPayload = json_decode($corrupt->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($corruptPayload['error'])->toContain('object bytes do not match')
        ->and($invalidPayload['error'])->toContain('--limit must be an integer')
        ->and(tellInspectionArenaSnapshot($workspace))->toBe($beforeCorrupt);
});

function tellInspectionProject(TellAgentFactory $factory): string
{
    $project = tellLastTemporaryRoot().'/workspace';
    mkdir($project, 0700, true);
    $factory->workspace()->initialize($project);

    return $project;
}

function tellInspectionWorkspace(TellAgentFactory $factory, string $project): TellWorkspace
{
    $workspace = $factory->workspace()->discover($project);
    if ($workspace === null) {
        throw new RuntimeException('Expected initialized Tell workspace to be discoverable.');
    }

    return $workspace;
}

/** @return array{0: CanonicalHash, 1: CanonicalHash, 2: CanonicalHash, 3: string} */
function tellInspectionSeedHistory(ArenaStore $arena): array
{
    $longAnswer = str_repeat('Zażółć ', 250);
    $root = $arena->put(new CanonicalConversationRoot(
        id: 'conversation-inspection',
        messages: [new CanonicalMessage(CanonicalRole::User, [new CanonicalTextPart('first question')])],
    ));
    $first = $arena->put(new CanonicalTurn(
        id: 'turn-inspection-one',
        lineage: new CanonicalLineage($root),
        messages: [new CanonicalMessage(CanonicalRole::Assistant, [new CanonicalTextPart('first answer')])],
    ));
    $second = $arena->put(new CanonicalTurn(
        id: 'turn-inspection-two',
        lineage: new CanonicalLineage($root, $first),
        messages: [
            new CanonicalMessage(CanonicalRole::User, [new CanonicalTextPart('second question')]),
            new CanonicalMessage(CanonicalRole::Assistant, [new CanonicalTextPart($longAnswer)]),
        ],
    ));
    $arena->compareAndSwap('main', null, $first);
    $arena->compareAndSwap('main', $first, $second);

    return [$root, $first, $second, $longAnswer];
}

/** @return array<string, string> */
function tellInspectionArenaSnapshot(TellWorkspace $workspace): array
{
    return tellInspectionDirectorySnapshot($workspace->paths->arena);
}

/** @return array<string, string> */
function tellInspectionDirectorySnapshot(string $directory): array
{
    $files = [];
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
            throw new RuntimeException("Unable to snapshot Tell arena file: {$file->getPathname()}");
        }
        $files[substr($file->getPathname(), strlen($root))] = $bytes;
    }
    ksort($files);

    return $files;
}
