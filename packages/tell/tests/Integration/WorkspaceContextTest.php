<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Tell\Canonical\CanonicalConversationRoot;
use Cognesy\Tell\Canonical\CanonicalHash;
use Cognesy\Tell\Canonical\CanonicalLineage;
use Cognesy\Tell\Canonical\CanonicalMessage;
use Cognesy\Tell\Canonical\CanonicalRole;
use Cognesy\Tell\Canonical\CanonicalTextPart;
use Cognesy\Tell\Canonical\CanonicalToolCall;
use Cognesy\Tell\Canonical\CanonicalToolResult;
use Cognesy\Tell\Canonical\CanonicalTurn;
use Cognesy\Tell\Command\ContextCommand;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Runtime\TellOptions;
use Cognesy\Tell\TellApplication;
use Cognesy\Tell\Workspace\ArenaStore;
use Cognesy\Tell\Workspace\SessionCompatibilityRef;
use Cognesy\Tell\Workspace\TellWorkspace;
use Cognesy\Tell\Workspace\WorkspaceConversationReader;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

it('inspects an empty workspace context without loop construction or persistence writes', function (): void {
    $factory = tellTestFactory(static function (AgentLoop $loop): AgentLoop {
        throw new RuntimeException('Context inspection must not build an agent loop.');
    });
    $project = tellContextProject($factory);
    $workspace = tellContextWorkspace($factory, $project);
    $arenaBefore = tellContextSnapshot($workspace->paths->arena);
    $homeBefore = tellContextSnapshot($factory->paths()->home);
    $application = new TellApplication($factory);
    $application->setAutoExit(false);
    $output = new BufferedOutput;

    $status = $application->runArgv(['tell', 'context', '--dir', $project, '--json'], $output);
    $payload = json_decode($output->fetch(), true, flags: JSON_THROW_ON_ERROR);

    expect($status)->toBe(0)
        ->and($payload['selector'])->toBe(['type' => 'main', 'name' => 'main'])
        ->and($payload['head'])->toBeNull()
        ->and($payload['root'])->toBeNull()
        ->and($payload['compaction'])->toBe(['turnCount' => 0, 'compactedFrom' => []])
        ->and($payload['compiled']['messageCount'])->toBe(0)
        ->and($payload['compiled']['toolCallCount'])->toBe(0)
        ->and($payload['compiled']['toolResultCount'])->toBe(0)
        ->and($payload['tokens']['modelCapacity']['value'])->toBeNull()
        ->and($payload['tokens']['modelCapacity']['status'])->toBe('unknown')
        ->and($payload['tokens']['context']['status'])->toBe('estimated')
        ->and($payload['tokens']['context']['estimator'])->toMatchArray([
            'identity' => 'gpt3-bpe',
            'encoding' => 'r50k_base',
        ])
        ->and(tellContextSnapshot($workspace->paths->arena))->toBe($arenaBefore)
        ->and(tellContextSnapshot($factory->paths()->home))->toBe($homeBefore);
});

it('reports the compiled AgentState, tool-heavy context, configured thresholds, and compaction provenance', function (): void {
    $factory = tellTestFactory();
    $project = tellContextProject($factory);
    $workspace = tellContextWorkspace($factory, $project);
    $arena = new ArenaStore($workspace);
    [, $discarded, $head] = tellContextSeedToolHistory($arena);
    $before = tellContextSnapshot($workspace->paths->arena);
    $homeBefore = tellContextSnapshot($factory->paths()->home);
    $tester = new CommandTester(new ContextCommand($factory));

    expect($tester->execute(['--dir' => $project, '--json' => true]))->toBe(0);
    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
    $definition = $factory->definition(new TellOptions(prompt: 'Inspect Tell context.', directory: $project));
    $state = (new DefinitionStateFactory)
        ->instantiateAgentState($definition)
        ->withMessages((new WorkspaceConversationReader($arena))->read()->history()->messages);

    expect($payload['head'])->toBe($head->toString())
        ->and($payload['compiled']['messageCount'])->toBe($state->messages()->count())
        ->and($payload['compiled'])->toMatchArray([
            'toolCallCount' => 2,
            'toolResultCount' => 2,
        ])
        ->and($payload['compaction'])->toBe([
            'turnCount' => 1,
            'compactedFrom' => [$discarded->toString()],
        ])
        ->and($payload['tokens']['context']['value'])->toBe(123)
        ->and($payload['tokens']['context']['status'])->toBe('estimated')
        ->and($payload['tokens']['configuredLimit']['status'])->toBe('exact')
        ->and($payload['tokens']['remainingConfiguredLimit']['status'])->toBe('estimated')
        ->and($payload['warningThresholds']['warning']['tokens'])->toBe(
            (int) floor($payload['tokens']['configuredLimit']['value'] * 0.80),
        )
        ->and($payload['configuration'])->toMatchArray([
            'connection' => 'openai',
            'driver' => 'openai',
        ])
        ->and(json_encode($payload, JSON_THROW_ON_ERROR))->not->toContain('tell-test-key')
        ->and(tellContextSnapshot($workspace->paths->arena))->toBe($before)
        ->and(tellContextSnapshot($factory->paths()->home))->toBe($homeBefore);
});

it('inspects a named canonical session without exposing its hashed compatibility ref', function (): void {
    $factory = tellTestFactory();
    $project = tellContextProject($factory);
    $workspace = tellContextWorkspace($factory, $project);
    $arena = new ArenaStore($workspace);
    $session = SessionId::from('review-1');
    $compatibility = new SessionCompatibilityRef($session);
    $root = $arena->put(new CanonicalConversationRoot(
        id: 'conversation-context-session',
        messages: [new CanonicalMessage(CanonicalRole::User, [new CanonicalTextPart('named context')])],
        session: $compatibility->metadata(),
    ));
    $arena->compareAndSwap($compatibility->refName(), null, $root);
    $before = tellContextSnapshot($workspace->paths->arena);
    $tester = new CommandTester(new ContextCommand($factory));

    expect($tester->execute(['--dir' => $project, '--session' => 'review-1', '--json' => true]))->toBe(0);
    $display = $tester->getDisplay();
    $payload = json_decode($display, true, flags: JSON_THROW_ON_ERROR);

    expect($payload['selector'])->toBe(['type' => 'session', 'name' => 'review-1'])
        ->and($payload['compiled']['messageCount'])->toBe(1)
        ->and(str_contains($display, $compatibility->refName()))->toBeFalse()
        ->and(tellContextSnapshot($workspace->paths->arena))->toBe($before);
});

it('keeps unknown configured capacity explicit and fails corrupt contexts without writing', function (): void {
    $factory = tellTestFactory();
    $project = tellContextProject($factory);
    $workspace = tellContextWorkspace($factory, $project);
    $arena = new ArenaStore($workspace);
    [$root] = tellContextSeedToolHistory($arena);
    $command = new ContextCommand($factory);
    $unknown = new CommandTester($command);

    expect($unknown->execute([
        '--dir' => $project,
        '--dsn' => 'driver=openai,model=unknown,contextLength=0',
        '--json' => true,
    ]))->toBe(0);
    $unknownPayload = json_decode($unknown->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
    expect($unknownPayload['tokens']['modelCapacity']['value'])->toBeNull()
        ->and($unknownPayload['tokens']['modelCapacity']['status'])->toBe('unknown')
        ->and($unknownPayload['tokens']['configuredLimit']['value'])->toBeNull()
        ->and($unknownPayload['tokens']['configuredLimit']['status'])->toBe('unknown')
        ->and($unknownPayload['tokens']['remainingConfiguredLimit']['value'])->toBeNull()
        ->and($unknownPayload['tokens']['remainingConfiguredLimit']['status'])->toBe('unknown');

    file_put_contents($arena->objectPath($root), '{"kind":"conversation"}');
    $before = tellContextSnapshot($workspace->paths->arena);
    $corrupt = new CommandTester($command);
    expect($corrupt->execute(['--dir' => $project, '--json' => true]))->toBe(1);
    $corruptPayload = json_decode($corrupt->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($corruptPayload['error'])->toContain('object bytes do not match')
        ->and(tellContextSnapshot($workspace->paths->arena))->toBe($before);
});

function tellContextProject(TellAgentFactory $factory): string
{
    $project = tellLastTemporaryRoot().'/context-workspace';
    mkdir($project, 0700, true);
    $factory->workspace()->initialize($project);

    return $project;
}

function tellContextWorkspace(TellAgentFactory $factory, string $project): TellWorkspace
{
    $workspace = $factory->workspace()->discover($project);
    if ($workspace === null) {
        throw new RuntimeException('Expected initialized Tell workspace to be discoverable.');
    }

    return $workspace;
}

/** @return array{0: CanonicalHash, 1: CanonicalHash, 2: CanonicalHash} */
function tellContextSeedToolHistory(ArenaStore $arena): array
{
    $root = $arena->put(new CanonicalConversationRoot(
        id: 'conversation-context',
        messages: [new CanonicalMessage(CanonicalRole::User, [new CanonicalTextPart('Calculate 2 + 2 and 3 + 3.')])],
    ));
    $discarded = $arena->put(new CanonicalTurn(
        id: 'turn-context-discarded',
        lineage: new CanonicalLineage($root),
        messages: [new CanonicalMessage(CanonicalRole::Assistant, [new CanonicalTextPart('This turn was compacted.')])],
    ));
    $head = $arena->put(new CanonicalTurn(
        id: 'turn-context-current',
        lineage: new CanonicalLineage($root, compactedFrom: [$discarded]),
        messages: [new CanonicalMessage(CanonicalRole::Assistant, [new CanonicalTextPart('The results are 4 and 6.')])],
        toolCalls: [
            new CanonicalToolCall('context-call-1', 'calculator', ['expression' => '2 + 2']),
            new CanonicalToolCall('context-call-2', 'calculator', ['expression' => '3 + 3']),
        ],
        toolResults: [
            new CanonicalToolResult('context-call-1', [new CanonicalTextPart('4')]),
            new CanonicalToolResult('context-call-2', [new CanonicalTextPart('6')]),
        ],
    ));
    $arena->compareAndSwap('main', null, $head);

    return [$root, $discarded, $head];
}

/** @return array<string, string> */
function tellContextSnapshot(string $directory): array
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
            throw new RuntimeException("Unable to snapshot Tell context file: {$file->getPathname()}");
        }
        $files[substr($file->getPathname(), strlen($root))] = $bytes;
    }
    ksort($files);

    return $files;
}
