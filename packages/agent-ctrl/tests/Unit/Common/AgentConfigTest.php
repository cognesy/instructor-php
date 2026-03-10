<?php

declare(strict_types=1);

namespace Tests\AgentCtrl\Unit\Common\AgentConfig;

use Cognesy\AgentCtrl\Builder\CodexBridgeBuilder;
use Cognesy\AgentCtrl\Config\AgentConfig;
use Cognesy\Sandbox\Enums\SandboxDriver;

it('hydrates agent config from laravel-style arrays', function () {
    $config = AgentConfig::fromArray([
        'model' => 'gpt-5-codex',
        'timeout' => '300',
        'directory' => '/tmp/workspace',
        'sandbox' => 'docker',
    ]);

    expect($config->model)->toBe('gpt-5-codex')
        ->and($config->timeout)->toBe(300)
        ->and($config->workingDirectory)->toBe('/tmp/workspace')
        ->and($config->sandboxDriver)->toBe(SandboxDriver::Docker)
        ->and($config->toArray())->toBe([
            'model' => 'gpt-5-codex',
            'timeout' => 300,
            'workingDirectory' => '/tmp/workspace',
            'sandboxDriver' => 'docker',
        ]);
});

it('merges overrides without nulling existing values', function () {
    $base = AgentConfig::fromArray([
        'model' => 'claude-sonnet',
        'timeout' => 300,
        'directory' => '/workspace',
        'sandbox' => 'host',
    ]);

    $merged = $base->withOverrides([
        'model' => '',
        'timeout' => '45',
        'directory' => null,
        'sandbox' => 'podman',
    ]);

    expect($merged->model)->toBe('claude-sonnet')
        ->and($merged->timeout)->toBe(45)
        ->and($merged->workingDirectory)->toBe('/workspace')
        ->and($merged->sandboxDriver)->toBe(SandboxDriver::Podman);
});

it('applies typed config to builders', function () {
    $builder = (new CodexBridgeBuilder())->withConfig(AgentConfig::fromArray([
        'model' => 'gpt-5-codex',
        'timeout' => 90,
        'directory' => '/repo',
        'sandbox' => 'bubblewrap',
    ]));

    expect(privateProperty($builder, 'model'))->toBe('gpt-5-codex')
        ->and(privateProperty($builder, 'timeout'))->toBe(90)
        ->and(privateProperty($builder, 'workingDirectory'))->toBe('/repo')
        ->and(privateProperty($builder, 'sandboxDriver'))->toBe(SandboxDriver::Bubblewrap);
});

function privateProperty(object $object, string $property): mixed
{
    $reflection = new \ReflectionClass($object);
    $refProperty = $reflection->getProperty($property);

    return $refProperty->getValue($object);
}
