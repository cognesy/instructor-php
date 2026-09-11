<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Tell\Adapter\Console\Command\ModelsCommand;
use Cognesy\Tell\Adapter\Console\Command\ProvidersCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

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
    file_put_contents($project . '/config/llm/models.json', json_encode([
        'version' => 'project-test',
        'models' => [[
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
        ]],
    ], JSON_THROW_ON_ERROR));

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
