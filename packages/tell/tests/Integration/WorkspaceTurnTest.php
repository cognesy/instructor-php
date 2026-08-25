<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Continuation\AgentStopException;
use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\AgentStep;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Messages\Content;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Tell\Canonical\CanonicalLineage;
use Cognesy\Tell\Canonical\CanonicalMessage;
use Cognesy\Tell\Canonical\CanonicalRole;
use Cognesy\Tell\Canonical\CanonicalTextPart;
use Cognesy\Tell\Canonical\CanonicalTurn;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\TellCommand;
use Cognesy\Tell\Tests\Support\RecordingDriver;
use Cognesy\Tell\Tests\Support\RequestRecorder;
use Cognesy\Tell\Workspace\ArenaHistoryCompiler;
use Cognesy\Tell\Workspace\ArenaStore;
use Cognesy\Tell\Workspace\TellWorkspace;
use HelgeSverre\Toon\Toon;
use Symfony\Component\Console\Tester\CommandTester;

it('continues a canonical workspace transcript with a fresh Tell process', function (): void {
    $recorder = new RequestRecorder;
    $firstDriver = new RecordingDriver($recorder, 'first answer');
    $firstFactory = tellTestFactory(static fn (AgentLoop $loop): AgentLoop => $loop->withDriver($firstDriver));
    $project = tellWorkspaceProject($firstFactory);

    $first = new CommandTester(new TellCommand($firstFactory));
    expect($first->execute(['prompt' => 'first turn', '--dir' => $project]))->toBe(0);

    $secondDriver = new RecordingDriver($recorder, 'second answer');
    $freshFactory = new TellAgentFactory(
        $firstFactory->paths(),
        static fn (AgentLoop $loop): AgentLoop => $loop->withDriver($secondDriver),
    );
    $second = new CommandTester(new TellCommand($freshFactory));
    expect($second->execute(['prompt' => 'second turn', '--dir' => $project]))->toBe(0);

    $secondRequest = array_map(
        static fn (array $message): array => [
            'role' => $message['role'],
            'content' => $message['content'],
        ],
        $recorder->requests[1],
    );
    $workspace = tellWorkspace($freshFactory, $project);
    $store = new ArenaStore($workspace);
    $head = $store->readRef()->head;
    if ($head === null) {
        throw new RuntimeException('Expected the completed workspace turn to have a canonical head.');
    }

    $history = (new ArenaHistoryCompiler)->compile($store, $head);

    expect($recorder->requests)->toHaveCount(2)
        ->and($secondRequest)->toContain(['role' => 'user', 'content' => 'first turn'])
        ->toContain(['role' => 'assistant', 'content' => 'first answer'])
        ->toContain(['role' => 'user', 'content' => 'second turn'])
        ->and(tellWorkspaceMessageProjection($history->messages))->toBe([
            ['role' => 'user', 'content' => 'first turn'],
            ['role' => 'assistant', 'content' => 'first answer'],
            ['role' => 'user', 'content' => 'second turn'],
            ['role' => 'assistant', 'content' => 'second answer'],
        ]);
});

it('compiles the same canonical head independently of provider selection', function (): void {
    $factory = tellTestFactory(static fn (AgentLoop $loop): AgentLoop => $loop->withDriver(FakeAgentDriver::fromResponses('durable answer')));
    $project = tellWorkspaceProject($factory);
    (new CommandTester(new TellCommand($factory)))->execute(['prompt' => 'durable prompt', '--dir' => $project]);

    $store = new ArenaStore(tellWorkspace($factory, $project));
    $head = $store->readRef()->head;
    if ($head === null) {
        throw new RuntimeException('Expected a canonical workspace head.');
    }

    $firstProviderView = tellWorkspaceMessageProjection((new ArenaHistoryCompiler)->compile($store, $head)->messages);
    $secondProviderView = tellWorkspaceMessageProjection((new ArenaHistoryCompiler)->compile($store, $head)->messages);

    expect($firstProviderView)->toBe($secondProviderView)
        ->toBe([
            ['role' => 'user', 'content' => 'durable prompt'],
            ['role' => 'assistant', 'content' => 'durable answer'],
        ]);
});

it('writes only semantic canonical data and excludes provider observations', function (): void {
    $driver = new class implements CanUseTools
    {
        public function useTools(AgentState $state): AgentState
        {
            return $state->withCurrentStep(new AgentStep(
                inputMessages: $state->messages(),
                outputMessages: Messages::fromString('semantic answer', 'assistant'),
                inferenceResponse: new InferenceResponse(
                    content: 'semantic answer',
                    reasoningContent: 'provider reasoning must remain outside arena',
                    usage: new InferenceUsage(41, 17),
                    responseData: HttpResponse::sync(
                        200,
                        ['authorization' => 'Bearer provider-wire-secret'],
                        'provider-wire-payload',
                    ),
                ),
            ));
        }
    };
    $factory = tellTestFactory(static fn (AgentLoop $loop): AgentLoop => $loop->withDriver($driver));
    $project = tellWorkspaceProject($factory);

    expect((new CommandTester(new TellCommand($factory)))->execute(['prompt' => 'persist only semantics', '--dir' => $project]))->toBe(0);

    $arena = implode('', tellWorkspaceArenaSnapshot(tellWorkspace($factory, $project)));
    expect($arena)->toContain('persist only semantics')
        ->toContain('semantic answer')
        ->not->toContain('provider reasoning must remain outside arena')
        ->not->toContain('provider-wire-secret')
        ->not->toContain('provider-wire-payload')
        ->not->toContain('"usage"')
        ->not->toContain('"createdAt"')
        ->not->toContain('"responseData"');
});

it('leaves an empty workspace arena unchanged when inference cannot publish', function (CanUseTools $driver, string $expectedError): void {
    $factory = tellTestFactory(static fn (AgentLoop $loop): AgentLoop => $loop->withDriver($driver));
    $project = tellWorkspaceProject($factory);
    $workspace = tellWorkspace($factory, $project);
    $before = tellWorkspaceArenaSnapshot($workspace);
    $tester = new CommandTester(new TellCommand($factory));

    $status = $tester->execute([
        'prompt' => 'must not publish',
        '--dir' => $project,
        '--output' => 'json',
    ]);
    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($status)->toBe(1)
        ->and($payload)->toHaveKey('error')
        ->and($payload['error'])->toContain($expectedError)
        ->and(tellWorkspaceArenaSnapshot($workspace))->toBe($before)
        ->and((new ArenaStore($workspace))->readRef()->head)->toBeNull();
})->with([
    'driver failure' => [
        new FakeAgentDriver([ScenarioStep::error('failed')]),
        'was not completed; arena head was left unchanged',
    ],
    'cancellation' => [
        new class implements CanUseTools
        {
            public function useTools(AgentState $state): AgentState
            {
                throw new AgentStopException(StopSignal::userRequested('cancelled'));
            }
        },
        'was not completed; arena head was left unchanged',
    ],
    'missing final response' => [
        new FakeAgentDriver([ScenarioStep::tool('')]),
        'has no final response; arena head was left unchanged',
    ],
    'unsupported canonical message' => [
        new class implements CanUseTools
        {
            public function useTools(AgentState $state): AgentState
            {
                return $state->withCurrentStep(new AgentStep(
                    inputMessages: $state->messages(),
                    outputMessages: new Messages(Message::fromContent(
                        new Content(
                            ContentPart::text('partly semantic'),
                            ContentPart::imageUrl('https://example.test/image.png'),
                        ),
                        'assistant',
                    )),
                    inferenceResponse: new InferenceResponse(content: 'partly semantic'),
                ));
            }
        },
        'only persists text message content',
    ],
]);

it('keeps the competing canonical head when a compare-and-swap race loses', function (): void {
    $factory = tellTestFactory(static fn (AgentLoop $loop): AgentLoop => $loop->withDriver(FakeAgentDriver::fromResponses('first answer')));
    $project = tellWorkspaceProject($factory);
    expect((new CommandTester(new TellCommand($factory)))->execute(['prompt' => 'first turn', '--dir' => $project]))->toBe(0);

    $workspace = tellWorkspace($factory, $project);
    $store = new ArenaStore($workspace);
    $previousHead = $store->readRef()->head;
    if ($previousHead === null) {
        throw new RuntimeException('Expected initial turn to publish a canonical head.');
    }
    $previousTurn = $store->get($previousHead);
    if (! $previousTurn instanceof CanonicalTurn) {
        throw new RuntimeException('Expected initial arena head to be a canonical turn.');
    }
    $winningHead = $store->put(new CanonicalTurn(
        id: 'turn-race-winner',
        lineage: new CanonicalLineage($previousTurn->lineage()->root(), $previousHead),
        messages: [
            new CanonicalMessage(CanonicalRole::User, [new CanonicalTextPart('racing turn')]),
            new CanonicalMessage(CanonicalRole::Assistant, [new CanonicalTextPart('race winner')]),
        ],
    ));

    $racingFactory = new TellAgentFactory(
        $factory->paths(),
        static function (AgentLoop $loop) use ($store, $previousHead, $winningHead): AgentLoop {
            return $loop
                ->withDriver(FakeAgentDriver::fromResponses('lost update'))
                ->onEvent(AgentExecutionCompleted::class, static function () use ($store, $previousHead, $winningHead): void {
                    $store->compareAndSwap('main', $previousHead, $winningHead);
                });
        },
    );
    $tester = new CommandTester(new TellCommand($racingFactory));
    $status = $tester->execute([
        'prompt' => 'concurrent turn',
        '--dir' => $project,
        '--output' => 'json',
    ]);
    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($status)->toBe(1)
        ->and($payload['error'])->toContain("Tell ref 'main' changed before it could be published.")
        ->and($store->readRef()->head?->equals($winningHead))->toBeTrue();
});

it('keeps successful workspace output contracts intact', function (string $mode): void {
    $factory = tellTestFactory(static fn (AgentLoop $loop): AgentLoop => $loop->withDriver(FakeAgentDriver::fromResponses('workspace answer')));
    $project = tellWorkspaceProject($factory);
    $tester = new CommandTester(new TellCommand($factory));
    $status = $tester->execute(['prompt' => 'render workspace answer', '--dir' => $project, '--output' => $mode]);
    $lines = array_values(array_filter(explode("\n", trim($tester->getDisplay()))));

    expect($status)->toBe(0);
    match ($mode) {
        'json' => expect(json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR)['answer'])->toBe('workspace answer'),
        'events' => expect(array_map(
            static fn (string $line): string => json_decode($line, true, flags: JSON_THROW_ON_ERROR)['event'],
            $lines,
        ))->toContain('AgentExecutionCompleted'),
        'text' => expect(trim($tester->getDisplay()))->toBe('workspace answer'),
        default => expect(Toon::decode($tester->getDisplay())['answer'])->toBe('workspace answer'),
    };
})->with(['toon', 'text', 'json', 'events']);

function tellWorkspaceProject(TellAgentFactory $factory): string
{
    $project = tellLastTemporaryRoot().'/workspace';
    mkdir($project, 0700, true);
    $factory->workspace()->initialize($project);

    return $project;
}

function tellWorkspace(TellAgentFactory $factory, string $project): TellWorkspace
{
    $workspace = $factory->workspace()->discover($project);
    if ($workspace === null) {
        throw new RuntimeException('Expected initialized Tell workspace to be discoverable.');
    }

    return $workspace;
}

/** @return array<string, string> */
function tellWorkspaceArenaSnapshot(TellWorkspace $workspace): array
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
        if ($bytes === false) {
            throw new RuntimeException("Unable to snapshot Tell arena file: {$file->getPathname()}");
        }
        $files[substr($file->getPathname(), strlen($root))] = $bytes;
    }
    ksort($files);

    return $files;
}

/** @return list<array{role: string, content: string}> */
function tellWorkspaceMessageProjection(Messages $messages): array
{
    return array_map(
        static fn (Message $message): array => [
            'role' => $message->role()->value,
            'content' => $message->content()->toString(),
        ],
        $messages->all(),
    );
}
