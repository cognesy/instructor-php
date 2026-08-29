<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Capability\Cancellation\InMemoryCancellationSource;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Agents\Hook\Enums\HookTrigger;
use Cognesy\Agents\Hook\Hooks\CallableHook;
use Cognesy\Agents\Hook\HookStack;
use Cognesy\Tell\Command\WorkspaceInspectionCommand;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Tell;
use Cognesy\Tell\Workspace\Arena\FilesystemArena;
use Cognesy\Tell\Workspace\Branch\Storage\BranchCurrentSelectionStore;
use Cognesy\Tell\Workspace\Branch\Storage\BranchStore;
use Cognesy\Tell\Workspace\Execution\TurnException;
use Cognesy\Tell\Workspace\WorkspaceState;
use Symfony\Component\Console\Tester\CommandTester;

/** @param list<string> $arguments @return array{code: int, output: string, errors: string} */
function tellChildProcess(string $project, array $arguments): array {
    $process = proc_open(
        [PHP_BINARY, dirname(__DIR__, 2) . '/bin/tell', ...$arguments],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $project,
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start Tell child inspection subprocess.');
    }

    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['code' => proc_close($process), 'output' => $output, 'errors' => $errors];
}

it('publishes a fresh delegated child on an isolated Tell-owned branch with inspectable provenance', function (): void {
    $parentDriver = FakeAgentDriver::fromSteps(
        ScenarioStep::toolCall('spawn_subagent', ['subagent' => 'default', 'prompt' => 'Inspect the selected context.', 'context' => 'fresh']),
        ScenarioStep::final('parent completed after delegation'),
    );
    $childDriver = FakeAgentDriver::fromSteps(
        ScenarioStep::final('child completed report'),
    );
    $build = 0;
    $factory = tellTestFactory(static function ($loop) use (&$build, $parentDriver, $childDriver) {
        return $loop->withDriver($build++ === 0 ? $parentDriver : $childDriver);
    });
    $project = tellLastTemporaryRoot() . '/project';
    mkdir($project, 0755, true);
    $workspace = $factory->workspace()->initialize($project)->workspace;

    $result = Tell::open($project, $factory)->run(TellRequest::prompt('Delegate this')->durable());
    $arena = new FilesystemArena($workspace);
    $branches = (new BranchStore($arena, new BranchCurrentSelectionStore($workspace)))->names();
    $child = $branches[0];
    $ref = $arena->readRef('branches/' . $child->toString());
    $provenance = $ref->provenance?->toArray();

    $history = new CommandTester(new WorkspaceInspectionCommand('history', $factory));
    $history->execute(['--dir' => $project, '--branch' => $child->toString(), '--json' => true]);
    $payload = json_decode($history->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
    $branchProcess = tellChildProcess($project, ['branch', 'show', $child->toString(), '--dir', $project, '--json']);
    $historyProcess = tellChildProcess($project, ['history', '--branch', $child->toString(), '--dir', $project, '--json']);
    $transcriptProcess = tellChildProcess($project, ['transcript', '--branch', $child->toString(), '--dir', $project, '--json']);
    $branchPayload = json_decode($branchProcess['output'], true, flags: JSON_THROW_ON_ERROR);
    $historyPayload = json_decode($historyProcess['output'], true, flags: JSON_THROW_ON_ERROR);
    $transcriptPayload = json_decode($transcriptProcess['output'], true, flags: JSON_THROW_ON_ERROR);

    expect($result->isCompleted())->toBeTrue()
        ->and($arena->readRef()->head)->not->toBeNull()
        ->and($child->toString())->toStartWith('agent-')
        ->and($ref->head)->not->toBeNull()
        ->and($provenance['source'])->toBe('agent')
        ->and($provenance['branch'])->toBe('main')
        ->and($provenance['head'])->toBeNull()
        ->and($provenance['metadata']['kind'])->toBe('delegated-child')
        ->and($provenance['metadata']['context'])->toBe('fresh')
        ->and($provenance['metadata']['definition'])->toBe('default')
        ->and($provenance['metadata']['executionId'])->toStartWith('delegation-')
        ->and($payload['selector'])->toBe([
            'type' => 'branch',
            'name' => $child->toString(),
            'source' => 'invocation',
        ])
        ->and($payload['totalCount'])->toBe(1)
        ->and($branchProcess['code'])->toBe(0)
        ->and($branchProcess['errors'])->toBe('')
        ->and($branchPayload['name'])->toBe($child->toString())
        ->and($historyProcess['code'])->toBe(0)
        ->and($historyProcess['errors'])->toBe('')
        ->and($historyPayload['totalCount'])->toBe(1)
        ->and($transcriptProcess['code'])->toBe(0)
        ->and($transcriptProcess['errors'])->toBe('')
        ->and($transcriptPayload['messageCount'])->toBeGreaterThan(0);
});

it('forks a child from the parent head before the parent advances', function (): void {
    $initial = FakeAgentDriver::fromSteps(ScenarioStep::final('initial parent context'));
    $parent = FakeAgentDriver::fromSteps(
        ScenarioStep::toolCall('spawn_subagent', ['subagent' => 'default', 'prompt' => 'Use the prior context.', 'context' => 'fork']),
        ScenarioStep::final('parent advances after delegation'),
    );
    $child = FakeAgentDriver::fromSteps(ScenarioStep::final('child inherited context'));
    $build = 0;
    $factory = tellTestFactory(static function ($loop) use (&$build, $initial, $parent, $child) {
        return $loop->withDriver([$initial, $parent, $child][$build++]);
    });
    $project = tellLastTemporaryRoot() . '/fork-project';
    mkdir($project, 0755, true);
    $workspace = $factory->workspace()->initialize($project)->workspace;
    $tell = Tell::open($project, $factory);

    $tell->run(TellRequest::prompt('Create context')->durable());
    $arena = new FilesystemArena($workspace);
    $before = $arena->readRef()->head;
    $result = $tell->run(TellRequest::prompt('Delegate with context')->durable());
    $childRef = $arena->readRef('branches/' . (new BranchStore($arena, new BranchCurrentSelectionStore($workspace)))->names()[0]->toString());

    expect($result->isCompleted())->toBeTrue()
        ->and($before)->not->toBeNull()
        ->and($childRef->provenance?->source)->toBe('agent')
        ->and($childRef->provenance?->head?->toString())->toBe($before->toString())
        ->and($childRef->head)->not->toBeNull()
        ->and($arena->readRef()->head?->toString())->not->toBe($before->toString());
});

it('runs a child coding tool under the inherited Tell policy and persists its semantic trace', function (): void {
    $parent = FakeAgentDriver::fromSteps(
        ScenarioStep::toolCall('spawn_subagent', ['subagent' => 'default', 'prompt' => 'Read the evidence file.', 'context' => 'fresh']),
        ScenarioStep::final('parent completed after delegated tool use'),
    );
    $child = FakeAgentDriver::fromSteps(
        ScenarioStep::toolCall('read_file', ['path' => 'evidence.txt']),
        ScenarioStep::final('child inspected the evidence'),
    );
    $build = 0;
    $factory = tellTestFactory(static function ($loop) use (&$build, $parent, $child) {
        return $loop->withDriver($build++ === 0 ? $parent : $child);
    });
    $project = tellLastTemporaryRoot() . '/child-tool-project';
    mkdir($project, 0755, true);
    file_put_contents($project . '/evidence.txt', "bounded evidence\n");
    $workspace = $factory->workspace()->initialize($project)->workspace;

    $result = Tell::open($project, $factory)->run(TellRequest::prompt('Delegate tool work')->durable());
    $arena = new FilesystemArena($workspace);
    $child = (new BranchStore($arena, new BranchCurrentSelectionStore($workspace)))->names()[0];
    $transcript = new CommandTester(new WorkspaceInspectionCommand('transcript', $factory));
    $transcript->execute(['--dir' => $project, '--branch' => $child->toString(), '--full' => true, '--json' => true]);
    $payload = json_decode($transcript->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
    $toolRows = array_values(array_filter(
        $payload['messages'],
        static fn (array $message): bool => $message['role'] === 'tool',
    ));

    expect($result->isCompleted())->toBeTrue()
        ->and($arena->readRef('branches/' . $child->toString())->head)->not->toBeNull()
        ->and($toolRows)->toHaveCount(1)
        ->and($toolRows[0]['content'])->toContain('bounded evidence');
});

it('rejects a stale child-head publication without moving the parent ref', function (): void {
    $initial = FakeAgentDriver::fromSteps(ScenarioStep::final('parent context before delegation'));
    $parent = FakeAgentDriver::fromSteps(
        ScenarioStep::toolCall('spawn_subagent', ['subagent' => 'default', 'prompt' => 'Publish from this fork.', 'context' => 'fork']),
        ScenarioStep::final('parent must not publish after child conflict'),
    );
    $child = FakeAgentDriver::fromSteps(ScenarioStep::final('child final response'));
    $workspace = null;
    $build = 0;
    $factory = tellTestFactory(static function ($loop) use (&$build, &$workspace, $initial, $parent, $child) {
        $index = $build++;
        $loop = $loop->withDriver([$initial, $parent, $child][$index]);
        if ($index !== 2) {
            return $loop;
        }

        $hooks = $loop->interceptor();
        assert($hooks instanceof HookStack);

        return $loop->withInterceptor($hooks->with(
            new CallableHook(static function ($context) use (&$workspace) {
                assert($workspace instanceof WorkspaceState);
                $arena = new FilesystemArena($workspace);
                $child = (new BranchStore($arena, new BranchCurrentSelectionStore($workspace)))->names()[0];
                $reference = $arena->readRef('branches/' . $child->toString());
                $arena->compareAndSwapToEmpty('branches/' . $child->toString(), $reference->head);

                return $context;
            }),
            HookTriggers::of(HookTrigger::AfterExecution),
            name: 'test_stale_child_head',
        ));
    });
    $project = tellLastTemporaryRoot() . '/stale-child-project';
    mkdir($project, 0755, true);
    $workspace = $factory->workspace()->initialize($project)->workspace;
    $tell = Tell::open($project, $factory);

    $tell->run(TellRequest::prompt('Create parent context')->durable());
    $arena = new FilesystemArena($workspace);
    $before = $arena->readRef()->head;

    expect(fn () => $tell->run(TellRequest::prompt('Delegate with a stale child head')->durable()))
        ->toThrow(TurnException::class);

    $child = (new BranchStore($arena, new BranchCurrentSelectionStore($workspace)))->names()[0];
    expect($before)->not->toBeNull()
        ->and($arena->readRef()->head?->toString())->toBe($before->toString())
        ->and($arena->readRef('branches/' . $child->toString())->head)->toBeNull();
});

it('does not reserve a child ref for an invalid delegated definition', function (): void {
    $parent = FakeAgentDriver::fromSteps(
        ScenarioStep::toolCall('spawn_subagent', ['subagent' => 'missing', 'prompt' => 'This must not create a branch.']),
        ScenarioStep::final('parent completed after rejected delegation'),
    );
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver($parent));
    $project = tellLastTemporaryRoot() . '/invalid-definition-project';
    mkdir($project, 0755, true);
    $workspace = $factory->workspace()->initialize($project)->workspace;

    expect(fn () => Tell::open($project, $factory)->run(
        TellRequest::prompt('Reject invalid child')->durable(),
    ))->toThrow(TurnException::class);
    $arena = new FilesystemArena($workspace);

    expect((new BranchStore($arena, new BranchCurrentSelectionStore($workspace)))->names())->toBe([])
        ->and($arena->readRef()->head)->toBeNull();
});

it('leaves a failed child at its initial head without publishing parent or child turns', function (): void {
    $parent = FakeAgentDriver::fromSteps(
        ScenarioStep::toolCall('spawn_subagent', ['subagent' => 'default', 'prompt' => 'This child fails.', 'context' => 'fresh']),
        ScenarioStep::final('parent completed after child failure'),
    );
    $child = FakeAgentDriver::fromSteps(ScenarioStep::error('child failed'));
    $build = 0;
    $factory = tellTestFactory(static function ($loop) use (&$build, $parent, $child) {
        return $loop->withDriver($build++ === 0 ? $parent : $child);
    });
    $project = tellLastTemporaryRoot() . '/failed-child-project';
    mkdir($project, 0755, true);
    $workspace = $factory->workspace()->initialize($project)->workspace;

    expect(fn () => Tell::open($project, $factory)->run(
        TellRequest::prompt('Delegate failing child')->durable(),
    ))->toThrow(TurnException::class);
    $arena = new FilesystemArena($workspace);
    $childRef = $arena->readRef('branches/' . (new BranchStore($arena, new BranchCurrentSelectionStore($workspace)))->names()[0]->toString());

    expect($childRef->head)->toBeNull()
        ->and($arena->readRef()->head)->toBeNull();
});

it('propagates cancellation into a child and leaves both refs unpublished', function (): void {
    $parent = FakeAgentDriver::fromSteps(
        ScenarioStep::toolCall('spawn_subagent', ['subagent' => 'default', 'prompt' => 'Cancelled child.', 'context' => 'fresh']),
        ScenarioStep::final('parent must not publish after cancellation'),
    );
    $child = FakeAgentDriver::fromSteps(ScenarioStep::final('child must not publish'));
    $cancellation = new InMemoryCancellationSource();
    $build = 0;
    $factory = tellTestFactory(static function ($loop) use (&$build, $parent, $child, $cancellation) {
        if ($build++ === 1) {
            $cancellation->cancel('child cancellation');
        }

        return $loop->withDriver($build === 1 ? $parent : $child);
    });
    $project = tellLastTemporaryRoot() . '/cancelled-child-project';
    mkdir($project, 0755, true);
    $workspace = $factory->workspace()->initialize($project)->workspace;

    expect(fn () => Tell::open($project, $factory, $cancellation)->run(
        TellRequest::prompt('Delegate cancelled child')->durable(),
    ))->toThrow(TurnException::class);
    $arena = new FilesystemArena($workspace);
    $childRef = $arena->readRef('branches/' . (new BranchStore($arena, new BranchCurrentSelectionStore($workspace)))->names()[0]->toString());

    expect($childRef->head)->toBeNull()
        ->and($arena->readRef()->head)->toBeNull();
});

it('rejects delegation from a child without reserving a grandchild branch', function (): void {
    $parent = FakeAgentDriver::fromSteps(
        ScenarioStep::toolCall('spawn_subagent', ['subagent' => 'default', 'prompt' => 'Delegate one level.', 'context' => 'fresh']),
        ScenarioStep::final('parent must not publish after depth rejection'),
    );
    $child = FakeAgentDriver::fromSteps(
        ScenarioStep::toolCall('spawn_subagent', ['subagent' => 'default', 'prompt' => 'This grandchild is forbidden.', 'context' => 'fresh']),
    );
    $build = 0;
    $factory = tellTestFactory(static function ($loop) use (&$build, $parent, $child) {
        return $loop->withDriver($build++ === 0 ? $parent : $child);
    });
    $project = tellLastTemporaryRoot() . '/depth-limited-child-project';
    mkdir($project, 0755, true);
    $workspace = $factory->workspace()->initialize($project)->workspace;

    expect(fn () => Tell::open($project, $factory)->run(
        TellRequest::prompt('Reject recursive delegation')->durable(),
    ))->toThrow(TurnException::class);
    $arena = new FilesystemArena($workspace);
    $childRef = $arena->readRef('branches/' . (new BranchStore($arena, new BranchCurrentSelectionStore($workspace)))->names()[0]->toString());

    expect((new BranchStore($arena, new BranchCurrentSelectionStore($workspace)))->names())->toHaveCount(1)
        ->and($childRef->head)->toBeNull()
        ->and($arena->readRef()->head)->toBeNull();
});
