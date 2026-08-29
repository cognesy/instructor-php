<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Continuation\AgentStopException;
use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\AgentStep;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Messages\Content;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Tell\Command\CompactCommand;
use Cognesy\Tell\Console\TellApplication;
use Cognesy\Tell\Console\TellCommand;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Tests\Support\RecordingDriver;
use Cognesy\Tell\Tests\Support\RequestRecorder;
use Cognesy\Tell\Workspace\Arena\FilesystemArena;
use Cognesy\Tell\Workspace\Arena\ObjectHash;
use Cognesy\Tell\Workspace\Arena\Record\ConversationRoot;
use Cognesy\Tell\Workspace\Arena\Record\Lineage;
use Cognesy\Tell\Workspace\Arena\Record\Message as RecordMessage;
use Cognesy\Tell\Workspace\Arena\Record\Role;
use Cognesy\Tell\Workspace\Arena\Record\TextPart;
use Cognesy\Tell\Workspace\Arena\Record\Turn;
use Cognesy\Tell\Workspace\Session\SessionRef;
use Cognesy\Tell\Workspace\WorkspaceState;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

it('compacts a canonical history into a provenance-linked summary and keeps its source immutable', function (): void {
    $factory = tellTestFactory(static fn (AgentLoop $loop): AgentLoop => $loop->withDriver(FakeAgentDriver::fromResponses('Carry forward the release decision and finish the migration.')));
    $project = tellCompactProject($factory);
    $workspace = tellCompactWorkspace($factory, $project);
    $arena = new FilesystemArena($workspace);
    [, $sourceHead] = tellCompactSeedHistory($arena);
    $sourceBytes = file_get_contents($arena->objectPath($sourceHead));
    $tester = new CommandTester(new CompactCommand($factory));

    $status = $tester->execute([
        'hint' => 'Prioritize release work',
        '--dir' => $project,
        '--json' => true,
    ]);
    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
    $head = $arena->readRef()->head;
    if ($head === null) {
        throw new RuntimeException('Expected compaction to publish a canonical head.');
    }
    $record = $arena->get($head);
    if (!$record instanceof Turn) {
        throw new RuntimeException('Expected compacted arena head to be a canonical turn.');
    }

    expect($status)->toBe(0)
        ->and($payload)->toMatchArray([
            'selector' => ['type' => 'main', 'name' => 'main'],
            'sourceHead' => $sourceHead->toString(),
            'head' => $head->toString(),
            'changed' => true,
            'before' => ['messageCount' => 4, 'turnCount' => 2],
            'after' => ['messageCount' => 2, 'turnCount' => 1],
        ])
        ->and($payload['after']['messageCount'])->toBeLessThan($payload['before']['messageCount'])
        ->and($record->lineage()->parent())->toBeNull()
        ->and(array_map(static fn ($hash): string => $hash->toString(), $record->lineage()->compactedFrom()))->toBe([$sourceHead->toString()])
        ->and($record->messages()[0]->parts()[0]->text())->toBe('Carry forward the release decision and finish the migration.')
        ->and(file_get_contents($arena->objectPath($sourceHead)))->toBe($sourceBytes);

    $recorder = new RequestRecorder();
    $freshFactory = new TellAgentFactory(
        $factory->paths(),
        static fn (AgentLoop $loop): AgentLoop => $loop->withDriver(new RecordingDriver($recorder, 'continued after compaction')),
    );
    $freshApplication = new TellApplication($freshFactory);
    $freshApplication->setAutoExit(false);
    $historyOutput = new BufferedOutput();
    $transcriptOutput = new BufferedOutput();
    $contextOutput = new BufferedOutput();
    expect($freshApplication->runArgv(['tell', 'history', '--dir', $project, '--json'], $historyOutput))->toBe(0)
        ->and($freshApplication->runArgv(['tell', 'transcript', '--dir', $project, '--json'], $transcriptOutput))->toBe(0)
        ->and($freshApplication->runArgv(['tell', 'context', '--dir', $project, '--json'], $contextOutput))->toBe(0);
    $historyPayload = json_decode($historyOutput->fetch(), true, flags: JSON_THROW_ON_ERROR);
    $transcriptPayload = json_decode($transcriptOutput->fetch(), true, flags: JSON_THROW_ON_ERROR);
    $contextPayload = json_decode($contextOutput->fetch(), true, flags: JSON_THROW_ON_ERROR);
    expect($historyPayload)->toMatchArray([
        'head' => $head->toString(),
        'count' => 1,
    ])
        ->and($historyPayload['turns'][0]['compactedFrom'])->toBe([$sourceHead->toString()])
        ->and($transcriptPayload['messageCount'])->toBe(2)
        ->and($contextPayload['head'])->toBe($head->toString())
        ->and($contextPayload['compaction'])->toBe([
            'turnCount' => 1,
            'compactedFrom' => [$sourceHead->toString()],
        ]);

    $continue = new CommandTester(new TellCommand($freshFactory));
    expect($continue->execute(['prompt' => 'continue', '--dir' => $project]))->toBe(0);

    $request = array_map(
        static fn (array $message): array => ['role' => $message['role'], 'content' => $message['content']],
        $recorder->requests[0],
    );
    expect($request)
        ->toContain(['role' => 'user', 'content' => 'initial constraints'])
        ->toContain(['role' => 'assistant', 'content' => 'Carry forward the release decision and finish the migration.'])
        ->toContain(['role' => 'user', 'content' => 'continue']);
});

it('compacts only the selected named workspace session', function (): void {
    $factory = tellTestFactory(static fn (AgentLoop $loop): AgentLoop => $loop->withDriver(FakeAgentDriver::fromResponses('Named session summary.')));
    $project = tellCompactProject($factory);
    $workspace = tellCompactWorkspace($factory, $project);
    $arena = new FilesystemArena($workspace);
    [, $mainHead] = tellCompactSeedHistory($arena);
    $session = SessionId::from('review-1');
    $sessionRef = new SessionRef($session);
    [, $sourceHead] = tellCompactSeedHistory($arena, $sessionRef->refName(), $sessionRef);
    $tester = new CommandTester(new CompactCommand($factory));

    $status = $tester->execute([
        '--dir' => $project,
        '--session' => 'review-1',
        '--json' => true,
    ]);
    $display = $tester->getDisplay();
    $payload = json_decode($display, true, flags: JSON_THROW_ON_ERROR);

    expect($status)->toBe(0)
        ->and($payload)->toMatchArray([
            'selector' => ['type' => 'session', 'name' => 'review-1'],
            'sourceHead' => $sourceHead->toString(),
        ])
        ->and($arena->readRef()->head?->equals($mainHead))->toBeTrue()
        ->and($arena->readRef($sessionRef->refName())->head?->equals($sourceHead))->toBeFalse()
        ->and(str_contains($display, $sessionRef->refName()))->toBeFalse();
});

it('keeps the selected ref on compaction failures and rejects oversized focus hints', function (CanUseTools $driver, string $error): void {
    $factory = tellTestFactory(static fn (AgentLoop $loop): AgentLoop => $loop->withDriver($driver));
    $project = tellCompactProject($factory);
    $workspace = tellCompactWorkspace($factory, $project);
    $arena = new FilesystemArena($workspace);
    [, $sourceHead] = tellCompactSeedHistory($arena);
    $before = tellCompactArenaSnapshot($workspace);
    $tester = new CommandTester(new CompactCommand($factory));

    $status = $tester->execute(['--dir' => $project, '--json' => true]);
    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($status)->toBe(1)
        ->and($payload['error'])->toContain($error)
        ->and($arena->readRef()->head?->equals($sourceHead))->toBeTrue()
        ->and(tellCompactArenaSnapshot($workspace))->toBe($before);
})->with([
    'driver failure' => [new FakeAgentDriver([ScenarioStep::error('failed')]), 'did not complete'],
    'cancellation' => [new class implements CanUseTools {
        public function useTools(AgentState $state): AgentState {
            throw new AgentStopException(StopSignal::userRequested('cancelled'));
        }
    }, 'did not complete'],
    'empty final summary' => [new FakeAgentDriver([ScenarioStep::final(' ')]), 'empty summary'],
    'non-text final summary' => [new class implements CanUseTools {
        public function useTools(AgentState $state): AgentState {
            return $state->withCurrentStep(new AgentStep(
                inputMessages: $state->messages(),
                outputMessages: new Messages(Message::fromContent(
                    new Content(ContentPart::imageUrl('https://example.test/summary.png')),
                    'assistant',
                )),
                inferenceResponse: new InferenceResponse(content: 'image'),
            ));
        }
    }, 'did not complete'],
    'invalid canonical serialization' => [new class implements CanUseTools {
        public function useTools(AgentState $state): AgentState {
            return $state->withCurrentStep(new AgentStep(
                inputMessages: $state->messages(),
                outputMessages: Messages::fromString("\xB1", 'assistant'),
                inferenceResponse: new InferenceResponse(content: "\xB1"),
            ));
        }
    }, 'could not canonically record'],
]);

it('does not persist focus hints or provider wire data in a compacted canonical record', function (): void {
    $driver = new class implements CanUseTools {
        public function useTools(AgentState $state): AgentState {
            return $state->withCurrentStep(new AgentStep(
                inputMessages: $state->messages(),
                outputMessages: Messages::fromString('Semantic summary only.', 'assistant'),
                inferenceResponse: new InferenceResponse(
                    content: 'Semantic summary only.',
                    responseData: HttpResponse::sync(200, ['authorization' => 'Bearer wire-secret'], 'wire-payload'),
                ),
            ));
        }
    };
    $factory = tellTestFactory(static fn (AgentLoop $loop): AgentLoop => $loop->withDriver($driver));
    $project = tellCompactProject($factory);
    $workspace = tellCompactWorkspace($factory, $project);
    $arena = new FilesystemArena($workspace);
    tellCompactSeedHistory($arena);
    $tester = new CommandTester(new CompactCommand($factory));

    expect($tester->execute([
        'hint' => 'hint-secret-should-not-be-persisted',
        '--dir' => $project,
        '--json' => true,
    ]))->toBe(0);

    $bytes = implode('', tellCompactArenaSnapshot($workspace));
    expect($bytes)
        ->toContain('Semantic summary only.')
        ->not->toContain('hint-secret-should-not-be-persisted')
        ->not->toContain('wire-secret')
        ->not->toContain('wire-payload');
});

it('preserves explicit compaction provenance when compacted again', function (): void {
    $factory = tellTestFactory(static fn (AgentLoop $loop): AgentLoop => $loop->withDriver(FakeAgentDriver::fromResponses('Stable compacted summary.')));
    $project = tellCompactProject($factory);
    $workspace = tellCompactWorkspace($factory, $project);
    $arena = new FilesystemArena($workspace);
    [, $sourceHead] = tellCompactSeedHistory($arena);
    $tester = new CommandTester(new CompactCommand($factory));

    expect($tester->execute(['--dir' => $project, '--json' => true]))->toBe(0);
    $firstHead = $arena->readRef()->head;
    if ($firstHead === null) {
        throw new RuntimeException('Expected first compacted head.');
    }
    expect($tester->execute(['--dir' => $project, '--json' => true]))->toBe(0);
    $secondHead = $arena->readRef()->head;
    if ($secondHead === null) {
        throw new RuntimeException('Expected second compacted head.');
    }
    $second = $arena->get($secondHead);
    if (!$second instanceof Turn) {
        throw new RuntimeException('Expected second compacted record.');
    }

    expect($firstHead->equals($sourceHead))->toBeFalse()
        ->and($secondHead->equals($firstHead))->toBeFalse()
        ->and(array_map(static fn ($hash): string => $hash->toString(), $second->lineage()->compactedFrom()))->toBe([$firstHead->toString()]);
});

it('keeps a competing head when explicit compaction loses its final compare-and-swap', function (): void {
    $factory = tellTestFactory();
    $project = tellCompactProject($factory);
    $workspace = tellCompactWorkspace($factory, $project);
    $arena = new FilesystemArena($workspace);
    [, $sourceHead] = tellCompactSeedHistory($arena);
    $source = $arena->get($sourceHead);
    if (!$source instanceof Turn) {
        throw new RuntimeException('Expected source canonical turn.');
    }
    $winner = $arena->put(new Turn(
        id: 'turn-compact-race-winner',
        lineage: new Lineage($source->lineage()->root(), $sourceHead),
        messages: [new RecordMessage(Role::Assistant, [new TextPart('Competing winner.')])],
    ));
    $racingFactory = new TellAgentFactory(
        $factory->paths(),
        static function (AgentLoop $loop) use ($arena, $sourceHead, $winner): AgentLoop {
            return $loop
                ->withDriver(FakeAgentDriver::fromResponses('Lost compacted summary.'))
                ->onEvent(AgentExecutionCompleted::class, static function () use ($arena, $sourceHead, $winner): void {
                    $arena->compareAndSwap('main', $sourceHead, $winner);
                });
        },
    );
    $tester = new CommandTester(new CompactCommand($racingFactory));

    $status = $tester->execute(['--dir' => $project, '--json' => true]);
    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($status)->toBe(1)
        ->and($payload['error'])->toContain("Tell ref 'main' changed before it could be published.")
        ->and($arena->readRef()->head?->equals($winner))->toBeTrue();
});

it('returns usage code two for a bounded compact hint', function (): void {
    $factory = tellTestFactory();
    $project = tellCompactProject($factory);
    $workspace = tellCompactWorkspace($factory, $project);
    $arena = new FilesystemArena($workspace);
    [, $sourceHead] = tellCompactSeedHistory($arena);
    $tester = new CommandTester(new CompactCommand($factory));

    $status = $tester->execute([
        'hint' => str_repeat('a', 501),
        '--dir' => $project,
        '--json' => true,
    ]);
    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($status)->toBe(2)
        ->and($payload['error'])->toContain('at most 500 characters')
        ->and($arena->readRef()->head?->equals($sourceHead))->toBeTrue();
});

function tellCompactProject(TellAgentFactory $factory): string {
    $project = tellLastTemporaryRoot() . '/compact-workspace';
    mkdir($project, 0700, true);
    $factory->workspace()->initialize($project);

    return $project;
}

function tellCompactWorkspace(TellAgentFactory $factory, string $project): WorkspaceState {
    $workspace = $factory->workspace()->discover($project);
    if ($workspace === null) {
        throw new RuntimeException('Expected initialized Tell workspace to be discoverable.');
    }

    return $workspace;
}

/** @return array{0: ObjectHash, 1: ObjectHash} */
function tellCompactSeedHistory(
    FilesystemArena $arena,
    string $ref = 'main',
    ?SessionRef $sessionRef = null,
): array {
    $suffix = str_replace('/', '-', $ref);
    $root = $arena->put(new ConversationRoot(
        id: 'conversation-compact-' . $suffix,
        messages: [new RecordMessage(Role::User, [new TextPart('initial constraints')])],
        session: $sessionRef?->metadata(),
    ));
    $first = $arena->put(new Turn(
        id: 'turn-compact-first-' . $suffix,
        lineage: new Lineage($root),
        messages: [
            new RecordMessage(Role::Assistant, [new TextPart('first completed decision')]),
            new RecordMessage(Role::User, [new TextPart('follow-up investigation')]),
        ],
    ));
    $head = $arena->put(new Turn(
        id: 'turn-compact-second-' . $suffix,
        lineage: new Lineage($root, $first),
        messages: [new RecordMessage(Role::Assistant, [new TextPart('second completed result')])],
    ));
    $arena->compareAndSwap($ref, null, $head);

    return [$root, $head];
}

/** @return array<string, string> */
function tellCompactArenaSnapshot(WorkspaceState $workspace): array {
    $files = [];
    $root = $workspace->paths->arena . DIRECTORY_SEPARATOR;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($workspace->paths->arena, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $bytes = file_get_contents($file->getPathname());
        if ($bytes === false) {
            throw new RuntimeException("Unable to snapshot compacted arena file: {$file->getPathname()}");
        }
        $files[substr($file->getPathname(), strlen($root))] = $bytes;
    }
    ksort($files);

    return $files;
}
