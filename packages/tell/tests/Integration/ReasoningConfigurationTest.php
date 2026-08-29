<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningEffort;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningSelection;
use Cognesy\Tell\Console\TellOptions;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Tell;

it('keeps typed request reasoning intent immutable across request builders', function (): void {
    $request = TellRequest::prompt('Reason deliberately.')
        ->withDirectory(tellTestProject())
        ->reasoningEffort(ReasoningEffort::High)
        ->model('deepseek-v4-pro')
        ->maxSteps(3);

    expect($request->reasoningEffort)->toBe(ReasoningEffort::High)
        ->and($request->reasoningEffortExplicit)->toBeTrue()
        ->and($request->toOptions()->reasoningEffortSource())->toBe('invocation');
});

it('persists branch reasoning intent and reports branch and invocation provenance', function (): void {
    $factory = tellTestFactory();
    $project = tellLastTemporaryRoot() . '/reasoning-workspace';
    mkdir($project, 0700, true);
    $tell = Tell::open($project, $factory);
    $tell->workspace()->initialize();
    $configuration = $tell->workspace()->configuration();
    $connection = $configuration->set('connection', 'deepseek', 0);
    $model = $configuration->set('model', 'deepseek-v4-flash', $connection->version);
    $stored = $configuration->set('reasoningEffort', 'medium', $model->version);

    $branch = $configuration->effective();
    $invocation = $configuration->effective(
        TellRequest::prompt('Use less reasoning.')->reasoningEffort(ReasoningEffort::Low),
    );

    expect($stored->values['reasoningEffort'])->toBe('medium')
        ->and($branch->values['reasoningEffort'])->toBe('medium')
        ->and($branch->provenance['reasoningEffort'])->toBe('branch')
        ->and($invocation->values['reasoningEffort'])->toBe('low')
        ->and($invocation->provenance['reasoningEffort'])->toBe('invocation');
});

it('exposes model-specific reasoning capability through the public catalogue', function (): void {
    $project = tellTestProject();
    $deepseek = Tell::open($project, tellTestFactory())->catalogue()->models('deepseek');
    $qwen = Tell::open($project, tellTestFactory())->catalogue()->models('qwen');

    expect($deepseek[0]['capabilities']['reasoningEffort'])->toBeTrue()
        ->and($qwen[0]['capabilities']['reasoningEffort'])->toBeTrue();
});

it('applies request-over-branch reasoning precedence', function (): void {
    $branch = (new TellOptions(
        prompt: 'branch effort',
        directory: tellTestProject(),
    ))->withBranchConfig(['reasoningEffort' => 'medium']);
    $invocation = (new TellOptions(
        prompt: 'invocation effort',
        directory: $branch->directory,
        reasoningEffort: ReasoningEffort::High,
        reasoningEffortExplicit: true,
    ))->withBranchConfig(['reasoningEffort' => 'low']);

    expect($branch->reasoningEffort)->toBe(ReasoningEffort::Medium)
        ->and($branch->reasoningEffortSource())->toBe('branch')
        ->and($invocation->reasoningEffort)->toBe(ReasoningEffort::High)
        ->and($invocation->reasoningEffortSource())->toBe('invocation');
});

it('passes typed effort to Polyglot without provider options in Tell', function (string $connection, string $model): void {
    $factory = tellTestFactory(credentials: [
        'DEEPSEEK_API_KEY' => 'tell-test-key',
        'QWEN_API_KEY' => 'tell-test-key',
    ]);
    $project = tellLastTemporaryRoot() . '/translation-project';
    mkdir($project, 0700, true);
    $options = new TellOptions(
        prompt: 'Translate only.',
        directory: $project,
        connection: $connection,
        model: $model,
        reasoningEffort: ReasoningEffort::Low,
        connectionExplicit: true,
        modelExplicit: true,
        reasoningEffortExplicit: true,
    );
    $definition = $factory->definition($options);
    $loop = $factory->build($options, $definition);
    $driver = $loop->driver();

    expect($definition->llmConfig?->options)->not()->toHaveKeys([
        'thinking',
        'reasoning_effort',
    ])->and($driver)->toBeInstanceOf(ToolCallingDriver::class)
        ->and($driver->reasoning())->toEqual(
            ReasoningSelection::effort(ReasoningEffort::Low),
        );
})->with([
    'DeepSeek V4' => ['deepseek', 'deepseek-v4-pro'],
    'Qwen 3' => ['qwen', 'qwen3.8-max'],
]);

it('rejects invalid branch values and unsupported models before fake inference', function (): void {
    $project = tellTestProject();
    $tell = Tell::testing($project, 'must remain unused');
    $tell->workspace()->initialize();
    $configuration = $tell->workspace()->configuration();

    expect(fn () => $configuration->set('reasoningEffort', 'unbounded', 0))
        ->toThrow(InvalidArgumentException::class, 'low, medium, high')
        ->and($configuration->show()->version)->toBe(0)
        ->and(fn () => (new TellOptions(prompt: 'invalid', directory: $project))
            ->withBranchConfig(['reasoningEffort' => 'unbounded']))
        ->toThrow(InvalidArgumentException::class, 'low, medium, high')
        ->and(fn () => $tell->run(
            TellRequest::prompt('Do not call the driver.')
                ->reasoningEffort(ReasoningEffort::Low),
        ))
        ->toThrow(InvalidArgumentException::class, 'not supported');
});
