<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Tool\Tools\FakeTool;
use Cognesy\Tell\Data\TellExecutionMode;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Runtime\TellRuntime;
use Cognesy\Tell\Tests\Support\RecordingDriver;
use Cognesy\Tell\Tests\Support\RequestRecorder;
use Cognesy\Tell\Workspace\Arena\FilesystemArena;

/**
 * A Tell runner must not use its generator as a transaction. Observing the
 * final checkpoint has to imply the turn is durable, the outcome has to be
 * reachable without draining, and a run torn down before it commits has to say
 * so rather than disappear.
 */
function commitProject(TellAgentFactory $factory): string {
    $project = tellLastTemporaryRoot() . '/workspace';
    mkdir($project, 0700, true);
    $factory->workspace()->initialize($project);

    return $project;
}

function commitArenaHead(TellAgentFactory $factory, string $project): ?string {
    $workspace = $factory->workspace()->discover($project);
    if ($workspace === null) {
        return null;
    }

    return (new FilesystemArena($workspace))->readOptionalRef('main')?->head?->toString();
}

function commitDurableRequest(string $prompt, string $project): TellRequest {
    return (new TellRequest(prompt: $prompt, mode: TellExecutionMode::Durable))->withDirectory($project);
}

/** A driver that spends several tool-calling steps before answering. */
function commitSteppingFactory(int $toolSteps): TellAgentFactory {
    $tools = (new Tools())->withTool(FakeTool::returning('ping', 'ping', 'pong'));
    $steps = array_fill(0, $toolSteps, ScenarioStep::toolCall('ping'));
    $steps[] = ScenarioStep::final('stepped answer');

    return tellTestFactory(static fn (AgentLoop $loop): AgentLoop => $loop->withDriver(
        new FakeAgentDriver(steps: $steps, tools: $tools),
    ));
}

it('publishes before the caller can observe the final checkpoint', function (): void {
    $recorder = new RequestRecorder();
    $factory = tellTestFactory(static fn (AgentLoop $loop): AgentLoop => $loop->withDriver(
        new RecordingDriver($recorder, 'committed answer'),
    ));
    $project = commitProject($factory);

    $stream = (new TellRuntime($factory))->stream(commitDurableRequest('committed turn', $project));

    // Stop exactly where a `foreach (... as $p) { if ($p->isCompleted()) break; }`
    // consumer stops: on the last checkpoint, without the advance past it.
    $progress = $stream->current();

    expect($progress->isCompleted())->toBeTrue();
    expect($recorder->requests)->toHaveCount(1);
    expect(commitArenaHead($factory, $project))
        ->not->toBeNull('observing a completed checkpoint must imply the turn is durable');

    unset($stream);

    expect(commitArenaHead($factory, $project))->not->toBeNull();
});

it('still publishes exactly once when the stream is fully drained', function (): void {
    $recorder = new RequestRecorder();
    $factory = tellTestFactory(static fn (AgentLoop $loop): AgentLoop => $loop->withDriver(
        new RecordingDriver($recorder, 'drained answer'),
    ));
    $project = commitProject($factory);

    $run = (new TellRuntime($factory))->start(commitDurableRequest('drained turn', $project));
    $checkpoints = 0;
    foreach ($run->checkpoints() as $_) {
        $checkpoints++;
    }

    expect($checkpoints)->toBeGreaterThan(0);
    expect($run->result()->isDurable())->toBeTrue();
    expect(trim($run->result()->text()))->toBe('drained answer');
    expect(commitArenaHead($factory, $project))->not->toBeNull();
});

it('hands an early-break consumer a result instead of an exception', function (): void {
    $recorder = new RequestRecorder();
    $factory = tellTestFactory(static fn (AgentLoop $loop): AgentLoop => $loop->withDriver(
        new RecordingDriver($recorder, 'early answer'),
    ));
    $project = commitProject($factory);

    $run = (new TellRuntime($factory))->start(commitDurableRequest('early turn', $project));
    foreach ($run->checkpoints() as $progress) {
        if ($progress->isCompleted()) {
            break; // never advances past the final checkpoint
        }
    }

    expect($run->isCommitted())->toBeTrue();
    expect(trim($run->result()->text()))->toBe('early answer');
    expect(commitArenaHead($factory, $project))->not->toBeNull();
});

it('reports a run torn down before it commits', function (): void {
    $factory = commitSteppingFactory(toolSteps: 3);
    $project = commitProject($factory);

    $run = (new TellRuntime($factory))->start(commitDurableRequest('abandoned turn', $project));
    $checkpoints = $run->checkpoints();
    $checkpoints->current();

    expect($run->isCommitted())->toBeFalse();

    unset($checkpoints);

    expect($run->isCommitted())->toBeFalse();
    expect(commitArenaHead($factory, $project))->toBeNull();
    expect(array_map(
        static fn (object $diagnostic): string => $diagnostic->code,
        $run->diagnostics(),
    ))->toContain('run_abandoned');
});

it('does not let a teardown failure escape the abandoning statement', function (): void {
    $factory = commitSteppingFactory(toolSteps: 3);
    $project = commitProject($factory);

    $run = (new TellRuntime($factory))->start(commitDurableRequest('teardown turn', $project));
    $checkpoints = $run->checkpoints();
    $checkpoints->current();

    // Removing the workspace under a live run makes any teardown that touches
    // the arena fail; dropping the generator must still be safe.
    tellRemoveDirectory($project);

    expect(static function () use (&$checkpoints): void {
        unset($checkpoints);
    })->not->toThrow(Throwable::class);
});
