<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Capability\Cancellation\InMemoryCancellationSource;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Tell\Data\TellEventEnvelope;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Capability\Workspace\Filesystem\FilesystemArena;
use Cognesy\Tell\Core\Workspace\Execution\TurnException;

it('streams bounded SDK checkpoints with stable redacted event envelopes', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('bounded answer')));
    $project = tellLastTemporaryRoot() . '/project';
    mkdir($project, 0755, true);
    tellTestWorkspaces()->initialize($project);
    $events = [];
    $stream = tellTestOpen($project, $factory)->runStream(
        TellRequest::prompt('Bound this run')
            ->durable()
            ->maxSteps(2)
            ->maxRetries(1)
            ->timeoutMs(1_000)
            ->maxOutputChars(100)
            ->maxToolOutputChars(50)
            ->maxToolCalls(1)
            ->onEvent(static function (TellEventEnvelope $event) use (&$events): void {
                $events[] = $event->toArray();
            }),
    );
    $checkpoints = iterator_to_array($stream);
    $result = $stream->getReturn();

    expect($checkpoints)->toHaveCount(1)
        ->and($result->isCompleted())->toBeTrue()
        ->and($events[0])->toMatchArray([
            'schema' => 'tell.event.v1',
            'branch' => 'main',
            'agent' => 'default',
        ])
        ->and(json_encode($events, JSON_THROW_ON_ERROR))->not->toContain('bounded answer');
});

it('never publishes a cancelled durable SDK run', function (): void {
    $cancellation = new InMemoryCancellationSource();
    $cancellation->cancel('caller deadline');
    $factory = tellTestFactory();
    $project = tellLastTemporaryRoot() . '/project';
    mkdir($project, 0755, true);
    $workspace = tellTestWorkspaces()->initialize($project)->workspace;

    expect(fn () => tellTestOpen($project, $factory, $cancellation)->run(
        TellRequest::prompt('Do not publish')->durable(),
    ))->toThrow(TurnException::class)
        ->and((new FilesystemArena($workspace))->readRef()->head)->toBeNull();
});

it('does not publish durable state when a public output policy is exceeded', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(
        FakeAgentDriver::fromResponses('This answer exceeds the configured output limit.'),
    ));
    $project = tellLastTemporaryRoot() . '/project';
    mkdir($project, 0755, true);
    $workspace = tellTestWorkspaces()->initialize($project)->workspace;

    expect(fn () => tellTestOpen($project, $factory)->run(
        TellRequest::prompt('Keep it short')->durable()->maxOutputChars(8),
    ))->toThrow(TurnException::class)
        ->and((new FilesystemArena($workspace))->readRef()->head)->toBeNull();
});
