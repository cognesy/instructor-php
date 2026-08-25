<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Tell\TellEvent;
use Cognesy\Tell\Tell;
use Cognesy\Tell\TellRequest;
use Cognesy\Tell\Workspace\ArenaStore;
use Cognesy\Tell\Workspace\SessionCompatibilityRef;
use Cognesy\Agents\Session\Data\SessionId;

it('runs an SDK request statelessly by default', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('SDK answer')));
    $project = tellLastTemporaryRoot().'/project';
    mkdir($project, 0755, true);

    $result = Tell::open($project, $factory)->run(TellRequest::prompt('What changed?'));

    expect($result->isCompleted())->toBeTrue()
        ->and($result->text())->toBe("SDK answer\n")
        ->and($result->isTransient())->toBeFalse()
        ->and($result->isDurable())->toBeFalse()
        ->and(is_dir($project.'/.tell'))->toBeFalse();
});

it('runs an SDK durable request through the workspace turn path', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('durable answer')));
    $project = tellLastTemporaryRoot().'/project';
    mkdir($project, 0755, true);
    $workspace = $factory->workspace()->initialize($project)->workspace;

    $result = Tell::open($project, $factory)->run(
        TellRequest::prompt('Remember this')->durable(),
    );

    expect($result->isCompleted())->toBeTrue()
        ->and($result->isDurable())->toBeTrue()
        ->and($result->workspace())->toBe($workspace->paths->root)
        ->and((new ArenaStore($workspace))->readRef()->head)->not->toBeNull();
});

it('runs an SDK named conversation through the workspace session path', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('session answer')));
    $project = tellLastTemporaryRoot().'/project';
    mkdir($project, 0755, true);
    $workspace = $factory->workspace()->initialize($project)->workspace;

    $result = Tell::open($project, $factory)->run(
        TellRequest::prompt('Continue')->durable('review'),
    );

    expect($result->isCompleted())->toBeTrue()
        ->and($result->isDurable())->toBeTrue()
        ->and($result->session())->toBe('review')
        ->and((new ArenaStore($workspace))->readOptionalRef(
            (new SessionCompatibilityRef(SessionId::from('review')))->refName(),
        ))->not->toBeNull();
});

it('runs a transient SDK request against workspace context without publishing', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('transient answer')));
    $project = tellLastTemporaryRoot().'/project';
    mkdir($project, 0755, true);
    $workspace = $factory->workspace()->initialize($project)->workspace;
    $tell = Tell::open($project, $factory);
    $tell->run(TellRequest::prompt('Persist this')->durable());
    $before = (new ArenaStore($workspace))->readRef()->toBytes();

    $result = $tell->run(TellRequest::prompt('Inspect safely')->transient());
    $after = (new ArenaStore($workspace))->readRef()->toBytes();

    expect($result->isCompleted())->toBeTrue()
        ->and($result->isTransient())->toBeTrue()
        ->and($result->isDurable())->toBeFalse()
        ->and($after)->toBe($before);
});

it('observes typed lifecycle events in agent source order', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('observed answer')));
    $project = tellLastTemporaryRoot().'/project';
    mkdir($project, 0755, true);
    $factory->workspace()->initialize($project);
    $events = [];

    Tell::open($project, $factory)->run(
        TellRequest::prompt('Observe this')->durable()->onEvent(static function (TellEvent $event) use (&$events): void {
            $events[] = $event;
        }),
    );

    $types = array_map(static fn (TellEvent $event): string => $event->type(), $events);
    $started = array_search('execution.started', $types, true);
    $step = array_search('step.completed', $types, true);
    $completed = array_search('execution.completed', $types, true);
    if (! is_int($started) || ! is_int($step) || ! is_int($completed)) {
        throw new RuntimeException('Expected complete Tell event lifecycle.');
    }
    expect($types)->toContain('execution.started')
        ->toContain('step.started')
        ->toContain('step.completed')
        ->toContain('execution.completed')
        ->and($started)->toBeLessThan($step)
        ->and($step)->toBeLessThan($completed)
        ->and($events[0]->agent())->toBe('default')
        ->and($events[0]->workspace())->toBe(realpath($project))
        ->and($events[0]->envelope()['branch'])->toBe('main')
        ->and($events[0]->envelope()['schema'])->toBe('tell.event.v1');
});

it('streams each completed checkpoint and returns the final result', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('streamed answer')));
    $project = tellLastTemporaryRoot().'/project';
    mkdir($project, 0755, true);

    $stream = Tell::open($project, $factory)->runStream(TellRequest::prompt('Stream this'));
    $progress = iterator_to_array($stream);
    $result = $stream->getReturn();

    expect($progress)->toHaveCount(1)
        ->and($progress[0]->stepCount())->toBe(1)
        ->and($progress[0]->isCompleted())->toBeTrue()
        ->and($result->isCompleted())->toBeTrue()
        ->and($result->text())->toBe("streamed answer\n");
});

it('does not publish a durable workspace turn when an observation listener fails', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('should not persist')));
    $project = tellLastTemporaryRoot().'/project';
    mkdir($project, 0755, true);
    $workspace = $factory->workspace()->initialize($project)->workspace;

    expect(static fn () => Tell::open($project, $factory)->run(
        TellRequest::prompt('Fail during observation')
            ->durable()
            ->onEvent(static fn (TellEvent $event) => throw new RuntimeException('observer failed')),
    ))->toThrow(RuntimeException::class, 'observer failed')
        ->and((new ArenaStore($workspace))->readRef()->head)->toBeNull();
});

it('controls an initialized workspace and named conversation without exposing arena details', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('conversation answer', 'compacted answer')));
    $project = tellLastTemporaryRoot().'/project';
    mkdir($project, 0755, true);
    $tell = Tell::open($project, $factory);

    $workspace = $tell->workspace()->initialize();
    $conversation = $tell->conversation('release-review');
    $send = $conversation->send(TellRequest::prompt('Record the release decision.'));
    $history = $conversation->history();
    $transcript = $conversation->transcript();
    $context = $conversation->context();
    $compaction = $conversation->compact(TellRequest::prompt('Compact release review.'));
    $cleared = $conversation->clear();

    expect($workspace->created)->toBeTrue()
        ->and($send->isDurable())->toBeTrue()
        ->and($send->session())->toBe('release-review')
        ->and($history->selector)->toBe(['type' => 'session', 'name' => 'release-review'])
        ->and($history->turns)->toHaveCount(1)
        ->and($transcript->messages)->toHaveCount(2)
        ->and($context->details['compiled']['messageCount'])->toBe(2)
        ->and($compaction->details['changed'])->toBeTrue()
        ->and($cleared->changed())->toBeTrue()
        ->and($cleared->isEmpty())->toBeTrue();
});
