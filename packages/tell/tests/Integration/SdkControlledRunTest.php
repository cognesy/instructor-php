<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Capability\Cancellation\InMemoryCancellationSource;
use Cognesy\Agents\Continuation\StopReason;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Tell\Data\TellEventEnvelope;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Data\TellPublicationStatus;
use Cognesy\Tell\Capability\Workspace\Filesystem\FilesystemArena;

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
    $terminal = array_values(array_filter(
        $events,
        static fn (array $event): bool => $event['terminal'] !== null,
    ));

    expect($checkpoints)->toHaveCount(1)
        ->and($result->isCompleted())->toBeTrue()
        ->and($events[0])->toMatchArray([
            'schema' => 'tell.event.v2',
            'branch' => 'main',
            'agent' => 'default',
        ])
        ->and($terminal)->toHaveCount(1)
        ->and($terminal[0]['metadata']['publication'])->toBe('published')
        ->and(json_encode($events, JSON_THROW_ON_ERROR))->not->toContain('bounded answer');
});

it('never publishes a cancelled durable SDK run', function (): void {
    $cancellation = new InMemoryCancellationSource();
    $cancellation->cancel('caller deadline');
    $factory = tellTestFactory();
    $project = tellLastTemporaryRoot() . '/project';
    mkdir($project, 0755, true);
    $workspace = tellTestWorkspaces()->initialize($project)->workspace;
    $events = [];

    $result = tellTestOpen($project, $factory, $cancellation)->run(
        TellRequest::prompt('Do not publish')->durable()->onEvent(
            static function (TellEventEnvelope $event) use (&$events): void {
                $events[] = $event->toArray();
            },
        ),
    );
    $terminal = array_values(array_filter(
        $events,
        static fn (array $event): bool => $event['terminal'] !== null,
    ));

    expect($result->status())->toBe(ExecutionStatus::Stopped)
        ->and($result->termination()->stopSignal?->reason)->toBe(StopReason::UserRequested)
        ->and($result->publication()->status)->toBe(TellPublicationStatus::NotAttempted)
        ->and($result->isPublished())->toBeFalse()
        ->and($result->trace()->executionId)->toBe($result->termination()->executionId)
        ->and($terminal)->toHaveCount(1)
        ->and($terminal[0]['metadata']['publication'])->toBe('not_attempted')
        ->and((new FilesystemArena($workspace))->readRef()->head)->toBeNull();
});

it('does not publish durable state when a public output policy is exceeded', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(
        FakeAgentDriver::fromResponses('This answer exceeds the configured output limit.'),
    ));
    $project = tellLastTemporaryRoot() . '/project';
    mkdir($project, 0755, true);
    $workspace = tellTestWorkspaces()->initialize($project)->workspace;

    $result = tellTestOpen($project, $factory)->run(
        TellRequest::prompt('Keep it short')->durable()->maxOutputChars(8),
    );

    expect($result->status())->toBe(ExecutionStatus::Stopped)
        ->and($result->termination()->stopSignal?->reason)->toBe(StopReason::OutputLimitReached)
        ->and($result->publication()->status)->toBe(TellPublicationStatus::NotAttempted)
        ->and($result->isPublished())->toBeFalse()
        ->and($result->trace()->executionId)->toBe($result->termination()->executionId)
        ->and((new FilesystemArena($workspace))->readRef()->head)->toBeNull();
});
