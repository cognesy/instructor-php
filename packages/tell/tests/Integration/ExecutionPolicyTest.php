<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Agents\Continuation\StopReason;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\ToolExecution;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Messages\ToolCall;
use Cognesy\Tell\Runtime\CanReadTellClock;
use Cognesy\Tell\Runtime\TellExecutionBudgetHook;
use Cognesy\Tell\Runtime\TellExecutionPolicy;
use Cognesy\Tell\Tell;
use Cognesy\Tell\TellRequest;
use Cognesy\Tell\Command\ConfigCommand;
use Cognesy\Tell\Workspace\ArenaStore;
use Cognesy\Tell\Workspace\BranchConfigStore;
use Cognesy\Tell\Workspace\WorkspaceTurnException;
use Cognesy\Utils\Result\Result;
use Symfony\Component\Console\Tester\CommandTester;

it('resolves finite policy values in CLI, branch, bundled precedence order', function (): void {
    tellTestFactory();
    $policy = TellExecutionPolicy::resolve([
        'maxRetries' => 2,
        'timeoutMs' => 1_500,
        'maxOutputChars' => 4_096,
        'maxToolOutputChars' => 2_048,
        'maxToolCalls' => 7,
    ], ['timeoutMs' => 800, 'maxToolCalls' => 3]);

    expect($policy->values())->toBe([
        'maxRetries' => 2,
        'timeoutMs' => 800,
        'maxOutputChars' => 4_096,
        'maxToolOutputChars' => 2_048,
        'maxToolCalls' => 3,
        'maxSpillBytes' => 200_000,
        'maxStubBytes' => 2_000,
    ])->and($policy->provenance())->toBe([
        'maxRetries' => 'branch',
        'timeoutMs' => 'cli',
        'maxOutputChars' => 'branch',
        'maxToolOutputChars' => 'branch',
        'maxToolCalls' => 'cli',
        'maxSpillBytes' => 'bundled',
        'maxStubBytes' => 'bundled',
    ]);
});

it('rejects non-positive and out-of-range execution policy values before inference', function (): void {
    foreach ([
        ['timeoutMs' => 0],
        ['maxOutputChars' => 0],
        ['maxToolOutputChars' => 0],
        ['maxToolCalls' => -1],
        ['maxRetries' => 11],
    ] as $values) {
        expect(fn () => TellExecutionPolicy::resolve([], $values))
            ->toThrow(\InvalidArgumentException::class);
    }
});

it('loads project and user policy defaults into effective branch configuration', function (): void {
    $factory = tellTestFactory();
    $project = tellLastTemporaryRoot().'/policy-defaults';
    mkdir($project, 0700, true);
    $factory->workspace()->initialize($project);
    $workspace = $factory->workspace()->discover($project) ?? throw new \RuntimeException('workspace missing');
    if (! is_dir($factory->paths()->configDirectory)) {
        mkdir($factory->paths()->configDirectory, 0700, true);
    }
    file_put_contents($factory->paths()->configDirectory.'/execution-defaults.json', json_encode([
        'schema' => 'tell.execution-defaults.v1',
        'values' => ['maxRetries' => 2, 'timeoutMs' => 1_500],
    ], JSON_THROW_ON_ERROR));
    if (! is_dir($workspace->paths->config)) {
        mkdir($workspace->paths->config, 0700, true);
    }
    file_put_contents($workspace->paths->config.'/defaults.json', json_encode([
        'schema' => 'tell.execution-defaults.v1',
        'values' => ['maxOutputChars' => 4_096],
    ], JSON_THROW_ON_ERROR));
    (new BranchConfigStore($workspace))->set('main', 'maxToolCalls', 7, 0);

    $tester = new CommandTester(new ConfigCommand($factory));
    expect($tester->execute(['action' => 'effective', '--dir' => $project, '--json' => true]))->toBe(0);
    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['values'])->toMatchArray([
        'maxRetries' => 2,
        'timeoutMs' => 1_500,
        'maxOutputChars' => 4_096,
        'maxToolCalls' => 7,
    ])->and($payload['provenance'])->toMatchArray([
        'maxRetries' => 'user',
        'timeoutMs' => 'user',
        'maxOutputChars' => 'project',
        'maxToolCalls' => 'branch',
    ])->and($payload['connectionResolution'])->toMatchArray([
        'connection' => 'openai',
        'provider' => 'openai',
        'model' => 'gpt-5.4-mini',
        'connectionSource' => 'bundled',
        'modelSource' => 'preset',
    ]);
});

it('keeps policy controls through fluent SDK request transformations', function (): void {
    tellTestFactory();
    $request = TellRequest::prompt('bounded')
        ->maxRetries(2)
        ->timeoutMs(1_000)
        ->maxOutputChars(500)
        ->maxToolOutputChars(200)
        ->maxToolCalls(3)
        ->durable()
        ->withDirectory(tellLastTemporaryRoot());

    expect($request->policy?->values())->toBe([
        'maxRetries' => 2,
        'timeoutMs' => 1_000,
        'maxOutputChars' => 500,
        'maxToolOutputChars' => 200,
        'maxToolCalls' => 3,
        'maxSpillBytes' => 200_000,
        'maxStubBytes' => 2_000,
    ]);
});

it('uses a deterministic clock, blocks excess tools, and truncates UTF-8 tool output', function (): void {
    $clock = new class implements CanReadTellClock {
        public int $now = 100;

        public function nowMs(): int
        {
            return $this->now;
        }
    };
    $hook = new TellExecutionBudgetHook(new TellExecutionPolicy(
        timeoutMs: 10,
        maxToolCalls: 1,
        maxToolOutputChars: 12,
    ), $clock);
    $state = AgentState::empty();
    $hook->handle(HookContext::beforeExecution($state));
    $clock->now = 110;
    $stopped = $hook->handle(HookContext::beforeStep($state));
    expect($stopped->state()->stopSignal()?->reason)->toBe(StopReason::TimeLimitReached);

    $call = ToolCall::fromArray(['name' => 'test', 'arguments' => []]);
    expect($hook->handle(HookContext::beforeToolUse($state, $call))->isToolExecutionBlocked())->toBeFalse()
        ->and($hook->handle(HookContext::beforeToolUse($state, $call))->isToolExecutionBlocked())->toBeTrue();

    $now = new \DateTimeImmutable();
    $execution = new ToolExecution($call, Result::success('zażółć-gęślą-jaźń'), $now, $now);
    $truncated = $hook->handle(HookContext::afterToolUse($state, $execution))->toolExecution();
    expect($truncated)->not->toBeNull()
        ->and(strlen((string) $truncated?->value()))->toBeLessThanOrEqual(12)
        ->and(preg_match('//u', (string) $truncated?->value()))->toBe(1);
});

it('does not publish a durable turn when the total model-output budget is exceeded', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(
        FakeAgentDriver::fromResponses('This response is intentionally too long.'),
    ));
    $project = tellLastTemporaryRoot().'/policy-workspace';
    mkdir($project, 0700, true);
    $factory->workspace()->initialize($project);

    expect(fn () => Tell::open($project, $factory)->run(
        TellRequest::prompt('Answer briefly')->durable()->maxOutputChars(8),
    ))->toThrow(WorkspaceTurnException::class);
    $workspace = $factory->workspace()->discover($project);

    expect((new ArenaStore($workspace ?? throw new \RuntimeException('workspace missing')))->readRef('main')->head)->toBeNull();
});
