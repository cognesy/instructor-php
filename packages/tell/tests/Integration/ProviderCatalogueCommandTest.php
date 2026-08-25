<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Tell\Command\ModelsCommand;
use Cognesy\Tell\Command\ProvidersCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

it('lists Polyglot-derived provider metadata without resolving or exposing preset secrets', function (): void {
    $factory = tellTestFactory();
    $project = tellLastTemporaryRoot().'/catalogue-project';
    mkdir($project.'/config/llm/presets', 0700, true);
    file_put_contents($project.'/config/llm/presets/qwen.yaml', <<<'YAML'
driver: qwen
apiUrl: https://example.invalid/v1
apiKey: ${CATALOGUE_SECRET_CANARY}
endpoint: /chat/completions
model: qwen3.8-max
contextLength: 123456
maxOutputLength: 789
YAML);

    $tester = new CommandTester(new ProvidersCommand($factory));
    expect($tester->execute(['--dir' => $project, '--fields' => 'connection,provider,source,defaultModel,contextCapacity,capabilities,unknown', '--json' => true]))->toBe(Command::SUCCESS);
    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
    $qwen = array_values(array_filter($payload['providers'], static fn (array $row): bool => $row['connection'] === 'qwen'))[0];

    expect($qwen['source'])->toBe('project')
        ->and($qwen['provider'])->toBe('qwen')
        ->and($qwen['defaultModel'])->toBe('qwen3.8-max')
        ->and($qwen['contextCapacity'])->toBe(123456)
        ->and($qwen['capabilities']['tools'])->toBeTrue()
        ->and($qwen['capabilities']['jsonSchema'])->toBeTrue()
        ->and($qwen['unknown']['thinking'])->toBe('not declared by Polyglot driver metadata')
        ->and($tester->getDisplay())->not->toContain('CATALOGUE_SECRET_CANARY')
        ->and($tester->getDisplay())->not->toContain('example.invalid');
});

it('filters models by provider or connection and rejects an unknown selector', function (): void {
    $factory = tellTestFactory();
    $project = tellLastTemporaryRoot().'/models-project';
    mkdir($project, 0700, true);
    $tester = new CommandTester(new ModelsCommand($factory));

    expect($tester->execute(['provider-or-connection' => 'deepseek', '--dir' => $project, '--fields' => 'connection,provider,defaultModel,availableModels,capabilities', '--json' => true]))->toBe(Command::SUCCESS);
    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
    $deepseek = array_values(array_filter($payload['models'], static fn (array $row): bool => $row['connection'] === 'deepseek'))[0];
    expect($payload['models'])->toHaveCount(2)
        ->and($deepseek['defaultModel'])->toBe('deepseek-v4-flash')
        ->and($deepseek['capabilities']['jsonSchema'])->toBeFalse();

    expect($tester->execute(['provider-or-connection' => 'does-not-exist', '--dir' => $project, '--json' => true]))->toBe(Command::INVALID)
        ->and($tester->getDisplay())->toContain('Unknown provider or connection');
});
