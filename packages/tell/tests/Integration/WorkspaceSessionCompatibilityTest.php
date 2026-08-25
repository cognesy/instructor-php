<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Session\Data\AgentSession;
use Cognesy\Agents\Session\Data\AgentSessionInfo;
use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\ToolCall;
use Cognesy\Messages\ToolCalls;
use Cognesy\Messages\ToolResult;
use Cognesy\Tell\Canonical\CanonicalConversationRoot;
use Cognesy\Tell\Command\SessionsCommand;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\TellCommand;
use Cognesy\Tell\Tests\Support\RecordingDriver;
use Cognesy\Tell\Tests\Support\RequestRecorder;
use Cognesy\Tell\Workspace\ArenaHistoryCompiler;
use Cognesy\Tell\Workspace\ArenaStore;
use Cognesy\Tell\Workspace\LegacySessionMigrator;
use Cognesy\Tell\Workspace\LegacySessionSource;
use Cognesy\Tell\Workspace\SessionCompatibilityRef;
use Cognesy\Tell\Workspace\TellWorkspace;
use Symfony\Component\Console\Tester\CommandTester;

it('imports a text and Unicode legacy session once without mutating its source bytes', function (): void {
    $bootstrap = tellTestFactory();
    $legacyBytes = tellWriteLegacySession(
        $bootstrap,
        'legacy-unicode',
        new Messages(
            Message::asUser('Zażółć gęślą jaźń'),
            Message::asAssistant('Unicode answer'),
        ),
    );
    $project = tellCompatibilityWorkspaceProject($bootstrap);
    $recorder = new RequestRecorder;
    $factory = new TellAgentFactory(
        $bootstrap->paths(),
        static fn ($loop) => $loop->withDriver(new RecordingDriver($recorder, 'workspace answer')),
    );
    $command = new TellCommand($factory);

    expect((new CommandTester($command))->execute([
        'prompt' => 'continue the legacy session',
        '--session' => 'legacy-unicode',
        '--dir' => $project,
    ]))->toBe(0);

    $workspace = tellCompatibilityWorkspace($factory, $project);
    $arena = new ArenaStore($workspace);
    $compatibility = new SessionCompatibilityRef(SessionId::from('legacy-unicode'));
    $head = $arena->readRef($compatibility->refName())->head;
    if ($head === null) {
        throw new RuntimeException('Expected migrated session head.');
    }
    $history = (new ArenaHistoryCompiler)->compile($arena, $head);
    $root = $history->root === null ? null : $arena->get($history->root);
    $firstRequest = tellCompatibilityRequestProjection($recorder->requests[0]);

    expect(file_get_contents($factory->paths()->sessions.'/legacy-unicode.json'))->toBe($legacyBytes)
        ->and($firstRequest)->toContain([
            'role' => 'user',
            'content' => 'Zażółć gęślą jaźń',
        ])
        ->and($firstRequest)->toContain([
            'role' => 'assistant',
            'content' => 'Unicode answer',
        ])
        ->and($root)->toBeInstanceOf(CanonicalConversationRoot::class)
        ->and($root?->session()?->name())->toBe('legacy-unicode')
        ->and($root?->session()?->sourceFingerprint()?->toString())->toBe(hash('sha256', $legacyBytes))
        ->and(tellCompatibilityMessageProjection($history->messages))->toBe([
            ['role' => 'user', 'content' => 'Zażółć gęślą jaźń'],
            ['role' => 'assistant', 'content' => 'Unicode answer'],
            ['role' => 'user', 'content' => 'continue the legacy session'],
            ['role' => 'assistant', 'content' => 'workspace answer'],
        ]);

    expect((new CommandTester($command))->execute([
        'prompt' => 'one more',
        '--session' => 'legacy-unicode',
        '--dir' => $project,
    ]))->toBe(0);
    $secondHead = $arena->readRef($compatibility->refName())->head;
    if ($secondHead === null) {
        throw new RuntimeException('Expected a later canonical session head.');
    }
    $secondHistory = (new ArenaHistoryCompiler)->compile($arena, $secondHead);
    $legacyMessages = array_filter(
        tellCompatibilityMessageProjection($secondHistory->messages),
        static fn (array $message): bool => $message['content'] === 'Unicode answer',
    );

    expect($legacyMessages)->toHaveCount(1)
        ->and(file_get_contents($factory->paths()->sessions.'/legacy-unicode.json'))->toBe($legacyBytes);

    $list = new CommandTester(new SessionsCommand($factory));
    expect($list->execute(['--dir' => $project, '--json' => true]))->toBe(0);
    $listPayload = json_decode($list->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
    $show = new CommandTester(new SessionsCommand($factory));
    expect($show->execute([
        'action' => 'show',
        'id' => 'legacy-unicode',
        '--dir' => $project,
        '--json' => true,
    ]))->toBe(0);
    $showPayload = json_decode($show->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($listPayload['sessions'][0])->toMatchArray([
        'sessionId' => 'legacy-unicode',
        'storage' => 'arena',
        'source' => 'legacy-migrated',
    ])
        ->and($showPayload)->toMatchArray([
            'sessionId' => 'legacy-unicode',
            'storage' => 'arena',
            'source' => 'legacy-migrated',
        ]);
});

it('imports empty and tool-using legacy histories into a workspace-compatible session ref', function (): void {
    $bootstrap = tellTestFactory();
    tellWriteLegacySession($bootstrap, 'empty-session', Messages::empty());
    tellWriteLegacySession(
        $bootstrap,
        'tool-session',
        new Messages(
            Message::asUser('calculate 2 + 2'),
            Message::asAssistant('')->withToolCalls(new ToolCalls(new ToolCall('calculator', ['expression' => '2 + 2'], 'legacy-call'))),
            Message::asTool('4')->withToolResult(new ToolResult('4', 'legacy-call', 'calculator')),
            Message::asAssistant('The answer is 4.'),
        ),
    );
    $project = tellCompatibilityWorkspaceProject($bootstrap);
    $recorder = new RequestRecorder;
    $factory = new TellAgentFactory(
        $bootstrap->paths(),
        static fn ($loop) => $loop->withDriver(new RecordingDriver($recorder, 'continued')),
    );

    expect((new CommandTester(new TellCommand($factory)))->execute([
        'prompt' => 'start from empty',
        '--session' => 'empty-session',
        '--dir' => $project,
    ]))->toBe(0)
        ->and((new CommandTester(new TellCommand($factory)))->execute([
            'prompt' => 'continue tool result',
            '--session' => 'tool-session',
            '--dir' => $project,
        ]))->toBe(0);

    $toolRequest = tellCompatibilityRequestProjection($recorder->requests[1]);
    expect($toolRequest)->toContain(['role' => 'user', 'content' => 'calculate 2 + 2'])
        ->toContain(['role' => 'tool', 'content' => '4'])
        ->toContain(['role' => 'assistant', 'content' => 'The answer is 4.']);

    $arena = new ArenaStore(tellCompatibilityWorkspace($factory, $project));
    foreach (['empty-session', 'tool-session'] as $name) {
        $ref = new SessionCompatibilityRef(SessionId::from($name));
        expect($arena->readRef($ref->refName())->head)->not->toBeNull();
    }
});

it('does not mutate a malformed legacy snapshot or publish a compatibility ref', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('unused')));
    if (! is_dir($factory->paths()->sessions)) {
        mkdir($factory->paths()->sessions, 0700, true);
    }
    $path = $factory->paths()->sessions.'/broken.json';
    $bytes = '{"broken":';
    file_put_contents($path, $bytes);
    $project = tellCompatibilityWorkspaceProject($factory);
    $workspace = tellCompatibilityWorkspace($factory, $project);
    $before = tellCompatibilityArenaSnapshot($workspace);
    $tester = new CommandTester(new TellCommand($factory));

    $status = $tester->execute([
        'prompt' => 'do not migrate',
        '--session' => 'broken',
        '--dir' => $project,
        '--output' => 'json',
    ]);
    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
    $ref = new SessionCompatibilityRef(SessionId::from('broken'));

    expect($status)->toBe(1)
        ->and($payload['error'])->toContain('cannot be migrated; it was left unchanged')
        ->and(file_get_contents($path))->toBe($bytes)
        ->and(tellCompatibilityArenaSnapshot($workspace))->toBe($before)
        ->and((new ArenaStore($workspace))->readOptionalRef($ref->refName()))->toBeNull();
});

it('makes repeated first-use imports converge on one deterministic canonical lineage', function (): void {
    $factory = tellTestFactory();
    tellWriteLegacySession(
        $factory,
        'convergent',
        new Messages(Message::asUser('legacy input'), Message::asAssistant('legacy output')),
    );
    $project = tellCompatibilityWorkspaceProject($factory);
    $arena = new ArenaStore(tellCompatibilityWorkspace($factory, $project));
    $compatibility = new SessionCompatibilityRef(SessionId::from('convergent'));
    $snapshot = (new LegacySessionSource($factory->paths()))->snapshot(SessionId::from('convergent'));
    if ($snapshot === null) {
        throw new RuntimeException('Expected legacy snapshot.');
    }

    $first = (new LegacySessionMigrator)->migrate($arena, $compatibility, $snapshot);
    $second = (new LegacySessionMigrator)->migrate($arena, $compatibility, $snapshot);
    $history = (new ArenaHistoryCompiler)->compile($arena, $second);

    expect($second->equals($first))->toBeTrue()
        ->and($arena->readRef($compatibility->refName())->head?->equals($first))->toBeTrue()
        ->and(tellCompatibilityMessageProjection($history->messages))->toBe([
            ['role' => 'user', 'content' => 'legacy input'],
            ['role' => 'assistant', 'content' => 'legacy output'],
        ]);
});

it('keeps the arena authoritative and warns when legacy source bytes diverge after migration', function (): void {
    $bootstrap = tellTestFactory();
    $legacyBytes = tellWriteLegacySession(
        $bootstrap,
        'divergent',
        new Messages(Message::asUser('original'), Message::asAssistant('answer')),
    );
    $project = tellCompatibilityWorkspaceProject($bootstrap);
    $factory = new TellAgentFactory(
        $bootstrap->paths(),
        static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('workspace answer')),
    );
    $first = new CommandTester(new TellCommand($factory));
    expect($first->execute([
        'prompt' => 'first workspace use',
        '--session' => 'divergent',
        '--dir' => $project,
    ]))->toBe(0);

    $path = $factory->paths()->sessions.'/divergent.json';
    file_put_contents($path, $legacyBytes."\n");
    $second = new CommandTester(new TellCommand($factory));
    expect($second->execute([
        'prompt' => 'arena wins',
        '--session' => 'divergent',
        '--dir' => $project,
        '--output' => 'json',
    ]))->toBe(0);
    $payload = json_decode($second->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['warnings'])->toBe([
        'Legacy session source changed after migration; workspace arena history remains authoritative.',
    ]);
});

function tellWriteLegacySession(
    TellAgentFactory $factory,
    string $id,
    Messages $messages,
): string {
    $sessionId = SessionId::from($id);
    $definition = $factory->definitions(tellLastTemporaryRoot())->get('default');
    $session = new AgentSession(
        AgentSessionInfo::fresh($sessionId, $definition->name, $definition->label()),
        $definition,
        AgentState::empty()->withMessages($messages),
    );
    if (! is_dir($factory->paths()->sessions)) {
        mkdir($factory->paths()->sessions, 0700, true);
    }
    $bytes = json_encode($session->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    file_put_contents($factory->paths()->sessions.'/'.$id.'.json', $bytes);

    return $bytes;
}

function tellCompatibilityWorkspaceProject(TellAgentFactory $factory): string
{
    $project = tellLastTemporaryRoot().'/workspace';
    mkdir($project, 0700, true);
    $factory->workspace()->initialize($project);

    return $project;
}

function tellCompatibilityWorkspace(TellAgentFactory $factory, string $project): TellWorkspace
{
    $workspace = $factory->workspace()->discover($project);
    if ($workspace === null) {
        throw new RuntimeException('Expected initialized Tell workspace to be discoverable.');
    }

    return $workspace;
}

/** @return array<string, string> */
function tellCompatibilityArenaSnapshot(TellWorkspace $workspace): array
{
    $files = [];
    $root = $workspace->paths->arena.DIRECTORY_SEPARATOR;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($workspace->paths->arena, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }
        $bytes = file_get_contents($file->getPathname());
        if (! is_string($bytes)) {
            throw new RuntimeException('Unable to snapshot Tell arena file.');
        }
        $files[substr($file->getPathname(), strlen($root))] = $bytes;
    }
    ksort($files);

    return $files;
}

/** @return list<array{role: string, content: string}> */
function tellCompatibilityMessageProjection(Messages $messages): array
{
    return array_map(
        static fn (Message $message): array => [
            'role' => $message->role()->value,
            'content' => $message->content()->toString(),
        ],
        $messages->all(),
    );
}

/** @param array<int, array<string, mixed>> $messages @return list<array{role: string, content: string}> */
function tellCompatibilityRequestProjection(array $messages): array
{
    return array_map(
        static fn (array $message): array => [
            'role' => (string) $message['role'],
            'content' => (string) $message['content'],
        ],
        $messages,
    );
}
