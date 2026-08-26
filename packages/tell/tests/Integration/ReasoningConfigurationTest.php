<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Tell\Runtime\TellOptions;
use Cognesy\Tell\Tell;
use Cognesy\Tell\TellReasoningEffort;
use Cognesy\Tell\TellRequest;

it('keeps typed request reasoning intent immutable across request builders', function (): void {
    $request = TellRequest::prompt('Reason deliberately.')
        ->withDirectory(tellTestProject())
        ->reasoningEffort(TellReasoningEffort::High)
        ->model('deepseek-v4-pro')
        ->maxSteps(3);

    expect($request->reasoningEffort)->toBe(TellReasoningEffort::High)
        ->and($request->reasoningEffortExplicit)->toBeTrue()
        ->and($request->toOptions()->reasoningEffortSource())->toBe('invocation');
});

it('persists branch reasoning intent and reports branch and invocation provenance', function (): void {
    $factory = tellTestFactory();
    $project = tellLastTemporaryRoot().'/reasoning-workspace';
    mkdir($project, 0700, true);
    $tell = Tell::open($project, $factory);
    $tell->workspace()->initialize();
    $configuration = $tell->workspace()->configuration();
    $connection = $configuration->set('connection', 'deepseek', 0);
    $model = $configuration->set('model', 'deepseek-v4-flash', $connection->version);
    $stored = $configuration->set('reasoningEffort', 'medium', $model->version);

    $branch = $configuration->effective();
    $invocation = $configuration->effective(
        TellRequest::prompt('Use less reasoning.')->reasoningEffort(TellReasoningEffort::Low),
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
        reasoningEffort: TellReasoningEffort::High,
        reasoningEffortExplicit: true,
    ))->withBranchConfig(['reasoningEffort' => 'low']);

    expect($branch->reasoningEffort)->toBe(TellReasoningEffort::Medium)
        ->and($branch->reasoningEffortSource())->toBe('branch')
        ->and($invocation->reasoningEffort)->toBe(TellReasoningEffort::High)
        ->and($invocation->reasoningEffortSource())->toBe('invocation');
});

it('translates typed effort into provider-native Polyglot options', function (string $connection, string $model, array $expected): void {
    $factory = tellTestFactory(credentials: [
        'DEEPSEEK_API_KEY' => 'tell-test-key',
        'QWEN_API_KEY' => 'tell-test-key',
    ]);
    $project = tellLastTemporaryRoot().'/translation-project';
    mkdir($project, 0700, true);
    $definition = $factory->definition(new TellOptions(
        prompt: 'Translate only.',
        directory: $project,
        connection: $connection,
        model: $model,
        reasoningEffort: TellReasoningEffort::Low,
        connectionExplicit: true,
        modelExplicit: true,
        reasoningEffortExplicit: true,
    ));

    expect($definition->llmConfig?->options)->toMatchArray($expected);
})->with([
    'DeepSeek V4' => ['deepseek', 'deepseek-v4-pro', [
        'thinking' => ['type' => 'enabled'],
        'reasoning_effort' => 'low',
    ]],
    'Qwen 3' => ['qwen', 'qwen3.8-max', [
        'thinking' => true,
        'reasoning_effort' => 'low',
    ]],
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
                ->reasoningEffort(TellReasoningEffort::Low),
        ))
        ->toThrow(InvalidArgumentException::class, 'not supported');
});
