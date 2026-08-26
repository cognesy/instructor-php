<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Tell\Tell;

it('configures branch-local runtime intent through the public SDK', function (): void {
    $factory = tellTestFactory();
    $project = tellLastTemporaryRoot().'/project';
    mkdir($project, 0755, true);
    $workspace = Tell::open($project, $factory)->workspace();
    $workspace->initialize();

    $main = $workspace->configuration();
    $initial = $main->show();
    $model = $main->set('model', 'deepseek-v4-flash', $initial->version);
    $timeout = $main->set('timeoutMs', 15_000, $model->version);
    $workspace->branches()->create('review');
    $review = $workspace->configuration('review');
    $effective = $review->effective();
    $deleted = $review->delete('timeoutMs', $effective->version);

    expect($initial->version)->toBe(0)
        ->and($timeout->version)->toBe(2)
        ->and($effective->branch)->toBe('review')
        ->and($effective->values['model'])->toBe('deepseek-v4-flash')
        ->and($effective->values['timeoutMs'])->toBe(15_000)
        ->and($effective->provenance['model'])->toBe('branch')
        ->and($effective->provenance['timeoutMs'])->toBe('branch')
        ->and($effective->precedence)->toBe(['invocation', 'branch', 'project', 'user', 'bundled'])
        ->and($deleted->version)->toBe(2)
        ->and($deleted->values)->not->toHaveKey('timeoutMs');
});

it('rejects stale and secret-bearing public branch configuration writes', function (): void {
    $factory = tellTestFactory();
    $project = tellLastTemporaryRoot().'/project';
    mkdir($project, 0755, true);
    $configuration = Tell::open($project, $factory)->workspace();
    $configuration->initialize();
    $configuration = $configuration->configuration();
    $configuration->set('model', 'deepseek-v4-flash', 0);

    expect(fn () => $configuration->set('timeoutMs', 10_000, 0))
        ->toThrow(RuntimeException::class, 'version conflict')
        ->and(fn () => $configuration->set('model', 'api_key=not-allowed', 1))
        ->toThrow(InvalidArgumentException::class, 'non-secret');
});
