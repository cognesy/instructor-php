<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Tell\Configuration\StandardTellConfigurationResolver;
use Cognesy\Tell\Configuration\StandardTellPathResolver;
use Cognesy\Tell\Configuration\TellPaths;
use Cognesy\Tell\Contracts\CanReadTellBranchConfiguration;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Workspace\Branch\TellBranchConfig;

it('resolves one explicit request branch host user bundled precedence with provenance', function (): void {
    $project = tellTestProject();
    $paths = new TellPaths($project . '/agents', $project . '/home');
    mkdir($paths->configDirectory, 0755, true);
    file_put_contents($paths->configDirectory . '/execution-defaults.json', json_encode([
        'schema' => 'tell.execution-defaults.v1',
        'values' => ['timeoutMs' => 40_000, 'maxToolCalls' => 20],
    ], JSON_THROW_ON_ERROR));
    $branches = new class implements CanReadTellBranchConfiguration {
        public function read(string $directory, ?string $branch = null): ?TellBranchConfig {
            return new TellBranchConfig($branch ?? 'main', 2, [
                'connection' => 'deepseek',
                'timeoutMs' => 20_000,
            ]);
        }
    };
    $resolver = new StandardTellConfigurationResolver(
        new StandardTellPathResolver($paths),
        $branches,
        ['timeoutMs' => 30_000, 'maxRetries' => 2, 'model' => 'host-model'],
    );

    $effective = $resolver->resolve(
        TellRequest::prompt('configure')
            ->withDirectory($project)
            ->connection('qwen')
            ->maxRetries(3),
    );

    expect($effective->request->connection)->toBe('qwen')
        ->and($effective->request->model)->toBe('host-model')
        ->and($effective->request->policy?->timeoutMs)->toBe(20_000)
        ->and($effective->request->policy?->maxRetries)->toBe(3)
        ->and($effective->request->policy?->maxToolCalls)->toBe(20)
        ->and($effective->provenance['connection'])->toBe('request')
        ->and($effective->provenance['model'])->toBe('host')
        ->and($effective->provenance['timeoutMs'])->toBe('branch')
        ->and($effective->provenance['maxRetries'])->toBe('request')
        ->and($effective->provenance['maxToolCalls'])->toBe('user');
});

it('accepts an absent optional branch reader without graph or resolution failure', function (): void {
    $project = tellTestProject();
    $paths = new TellPaths($project . '/agents', $project . '/home');
    $effective = (new StandardTellConfigurationResolver(new StandardTellPathResolver($paths)))
        ->resolve(TellRequest::prompt('no branch')->withDirectory($project));

    expect($effective->branch)->toBeNull()
        ->and($effective->request->connection)->toBe('openai');
});
