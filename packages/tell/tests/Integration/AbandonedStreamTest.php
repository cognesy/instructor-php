<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Agents\AgentLoop;
use Cognesy\Tell\Runtime\TellRuntime;
use Cognesy\Tell\Tests\Support\RecordingDriver;
use Cognesy\Tell\Tests\Support\RequestRecorder;
use Cognesy\Tell\TellExecutionMode;
use Cognesy\Tell\TellRequest;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Workspace\ArenaStore;

/** Reads the arena head for the default branch, or null when nothing was published. */
function abandonArenaHead(TellAgentFactory $factory, string $project): ?string
{
    $workspace = $factory->workspace()->discover($project);
    if ($workspace === null) {
        return null;
    }
    $ref = (new ArenaStore($workspace))->readOptionalRef('main');

    return $ref?->head?->toString();
}

function abandonProject(TellAgentFactory $factory): string
{
    $project = tellLastTemporaryRoot().'/workspace';
    mkdir($project, 0700, true);
    $factory->workspace()->initialize($project);

    return $project;
}

it('publishes a workspace turn when the stream is drained', function (): void {
    $recorder = new RequestRecorder;
    $driver = new RecordingDriver($recorder, 'drained answer');
    $factory = tellTestFactory(static fn (AgentLoop $loop): AgentLoop => $loop->withDriver($driver));
    $project = abandonProject($factory);

    expect(abandonArenaHead($factory, $project))->toBeNull();

    $stream = (new TellRuntime($factory))->stream(
        (new TellRequest(prompt: 'drained turn', mode: TellExecutionMode::Durable))->withDirectory($project),
    );
    foreach ($stream as $_) {
    }

    expect(abandonArenaHead($factory, $project))->not->toBeNull();
});

it('drops a completed workspace turn when the caller stops at the last checkpoint', function (): void {
    $recorder = new RequestRecorder;
    $driver = new RecordingDriver($recorder, 'abandoned answer');
    $factory = tellTestFactory(static fn (AgentLoop $loop): AgentLoop => $loop->withDriver($driver));
    $project = abandonProject($factory);

    $stream = (new TellRuntime($factory))->stream(
        (new TellRequest(prompt: 'abandoned turn', mode: TellExecutionMode::Durable))->withDirectory($project),
    );

    // Take the checkpoints the caller can see, then stop - exactly what a
    // `foreach (... as $progress) { if ($done) break; }` consumer does.
    $progress = $stream->current();

    expect($progress)->not->toBeNull();
    expect($progress->isCompleted())->toBeTrue();
    expect($recorder->requests)->toHaveCount(1); // inference already happened, and was paid for

    unset($stream);

    // The turn completed, yet nothing reached the arena.
    expect(abandonArenaHead($factory, $project))->toBeNull();
});
