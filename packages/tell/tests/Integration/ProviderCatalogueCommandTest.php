<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Tell\Adapter\Console\Command\ModelsCommand;
use Cognesy\Tell\Adapter\Console\Command\ProvidersCommand;
use Cognesy\Polyglot\Inference\Creation\InferenceDriverRegistry;
use Cognesy\Polyglot\Inference\Models\SupportStatus;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

it('lists Polyglot-derived provider metadata without resolving or exposing preset secrets', function (): void {
    $factory = tellTestFactory();
    $project = tellLastTemporaryRoot() . '/catalogue-project';
    mkdir($project . '/config/llm/presets', 0700, true);
    file_put_contents($project . '/config/llm/presets/qwen.yaml', <<<'YAML'
driver: qwen
apiUrl: https://example.invalid/v1
apiKey: ${CATALOGUE_SECRET_CANARY}
endpoint: /chat/completions
model: qwen3.8-max
YAML);
    mkdir($project . '/config/llm/models/qwen', 0700, true);
    file_put_contents($project . '/config/llm/models/qwen/qwen3.8-max.yaml', \Symfony\Component\Yaml\Yaml::dump([
        'schemaVersion' => 1,
        'version' => 'project-test',
        'profile' => [
            'driver' => 'qwen',
            'model' => 'qwen3.8-max',
            'status' => 'supported',
            'limits' => ['contextWindow' => 123456, 'maxOutput' => 789],
            'capabilities' => [
                'streaming' => 'supported',
                'tools' => 'supported',
                'jsonSchema' => 'supported',
            ],
            'source' => 'project-test',
        ],
    ], 12, 2));

    $tester = new CommandTester(new ProvidersCommand(tellTestProviderCatalogue($factory)));
    expect($tester->execute(['--dir' => $project, '--fields' => 'connection,provider,source,defaultModel,contextCapacity,capabilities,catalogSource,catalogVersion', '--json' => true]))->toBe(Command::SUCCESS);
    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
    $qwen = array_values(array_filter($payload['providers'], static fn (array $row): bool => $row['connection'] === 'qwen'))[0];

    expect($qwen['source'])->toBe('project')
        ->and($qwen['provider'])->toBe('qwen')
        ->and($qwen['defaultModel'])->toBe('qwen3.8-max')
        ->and($qwen['contextCapacity'])->toBe(123456)
        ->and($qwen['capabilities']['tools'])->toBeTrue()
        ->and($qwen['capabilities']['jsonSchema'])->toBeTrue()
        ->and($qwen['catalogSource'])->toBe('project-test')
        ->and($qwen['catalogVersion'])->toBe('project-test')
        ->and($tester->getDisplay())->not->toContain('CATALOGUE_SECRET_CANARY')
        ->and($tester->getDisplay())->not->toContain('example.invalid');
});

it('filters models by provider or connection and rejects an unknown selector', function (): void {
    $factory = tellTestFactory();
    $project = tellLastTemporaryRoot() . '/models-project';
    mkdir($project, 0700, true);
    $tester = new CommandTester(new ModelsCommand(tellTestProviderCatalogue($factory)));

    expect($tester->execute(['provider-or-connection' => 'deepseek', '--dir' => $project, '--fields' => 'provider,model,defaultFor,capabilities', '--json' => true]))->toBe(Command::SUCCESS);
    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
    $deepseek = array_values(array_filter($payload['models'], static fn (array $row): bool => $row['model'] === 'deepseek-v4-flash'))[0];
    expect($payload['models'])->not->toBeEmpty()
        ->and(array_unique(array_column($payload['models'], 'provider')))->toBe(['deepseek'])
        ->and($deepseek['provider'])->toBe('deepseek')
        ->and($deepseek['defaultFor'])->toContain('deepseek')
        ->and($deepseek['capabilities']['jsonSchema'])->toBeFalse();

    expect($tester->execute(['provider-or-connection' => 'does-not-exist', '--dir' => $project, '--json' => true]))->toBe(Command::INVALID)
        ->and($tester->getDisplay())->toContain('Unknown provider or connection');
});

it('keeps packaged vendor user and project records isolated by target project', function (): void {
    $factory = tellTestFactory();
    $catalogue = tellTestProviderCatalogue($factory);
    $first = tellLastTemporaryRoot() . '/catalogue-first';
    $second = tellLastTemporaryRoot() . '/catalogue-second';
    mkdir($first, 0700, true);
    mkdir($second, 0700, true);

    writeTellCatalogRecord(
        $factory->paths()->models,
        'qwen',
        'qwen3.8-max',
        200,
        'user-record',
    );
    writeTellCatalogRecord(
        $first . '/config/llm/models',
        'qwen',
        'qwen3.8-max',
        100,
        'first-project',
    );
    writeTellCatalogRecord(
        $second . '/vendor/cognesy/instructor-php/packages/polyglot/resources/config/llm/models',
        'openai',
        'gpt-4o-mini',
        300,
        'second-vendor',
    );

    $firstCatalog = $catalogue->catalog($first);
    $secondCatalog = $catalogue->catalog($second);

    expect($firstCatalog)->toBe($catalogue->catalog($first . '/.'))
        ->and($secondCatalog)->not->toBe($firstCatalog)
        ->and($firstCatalog->find('qwen', 'qwen3.8-max')->limits->contextWindow)->toBe(100)
        ->and($firstCatalog->find('qwen', 'qwen3.8-max')->source)->toBe('first-project')
        ->and($secondCatalog->find('qwen', 'qwen3.8-max')->limits->contextWindow)->toBe(200)
        ->and($secondCatalog->find('qwen', 'qwen3.8-max')->source)->toBe('user-record')
        ->and($firstCatalog->find('openai', 'gpt-4o-mini')->source)->toBe('upstream-reviewed')
        ->and($secondCatalog->find('openai', 'gpt-4o-mini')->limits->contextWindow)->toBe(300)
        ->and($secondCatalog->find('openai', 'gpt-4o-mini')->source)->toBe('second-vendor');
});

it('does not invent Tell metadata from a process-local custom driver registry', function (): void {
    $factory = tellTestFactory();
    $project = tellLastTemporaryRoot() . '/runtime-registry-project';
    mkdir($project . '/config/llm/presets', 0700, true);
    file_put_contents($project . '/config/llm/presets/runtime-only.yaml', <<<'YAML'
driver: runtime-only
apiUrl: https://example.invalid/v1
apiKey: runtime-only-key
endpoint: /chat/completions
model: private-model
YAML);
    $runtimeRegistry = InferenceDriverRegistry::default()->withDriver(
        'runtime-only',
        static fn () => new FakeInferenceDriver(),
    );
    $catalogue = tellTestProviderCatalogue($factory);
    $connections = $catalogue->connections($project)['connections'];
    $runtimeOnly = array_values(array_filter(
        $connections,
        static fn (array $row): bool => $row['connection'] === 'runtime-only',
    ))[0];

    expect($runtimeRegistry->has('runtime-only'))->toBeTrue()
        ->and($runtimeOnly['provider'])->toBe('runtime-only')
        ->and($runtimeOnly['status'])->toBe('unknown')
        ->and($runtimeOnly['capabilities']['tools'])->toBeNull()
        ->and($catalogue->catalog($project)->find('runtime-only', 'private-model')->status)
        ->toBe(SupportStatus::Unknown);
});

function writeTellCatalogRecord(
    string $root,
    string $driver,
    string $model,
    int $contextWindow,
    string $source,
): void {
    $directory = $root . '/' . rawurlencode($driver);
    if (!is_dir($directory)) {
        mkdir($directory, 0700, true);
    }
    file_put_contents($directory . '/' . rawurlencode($model) . '.yaml', Yaml::dump([
        'schemaVersion' => 1,
        'version' => $source,
        'profile' => [
            'driver' => $driver,
            'model' => $model,
            'status' => 'supported',
            'limits' => ['contextWindow' => $contextWindow],
            'source' => $source,
        ],
    ], 12, 2));
}
