<?php

declare(strict_types=1);

use Cognesy\Config\BasePath;
use Cognesy\Config\EnvTemplate;
use Cognesy\Config\Secrets\ArraySecretSource;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\DecisionProvider;
use Cognesy\Polyglot\Support\Redaction\SensitiveDataRedactor;
use Symfony\Component\Yaml\Yaml;

it('discovers the bundled preset and default selector from an isolated consumer root', function () {
    $root = decisionConfigConsumerRoot();
    BasePath::set($root);
    $template = new EnvTemplate(new ArraySecretSource('test', []));

    try {
        $preset = DecisionConfig::fromPreset('typesafe', template: $template);
        $default = DecisionConfig::fromDefaults($template);

        expect(DecisionConfig::presetNames())->toContain('typesafe')
            ->and($preset->driver)->toBe('typesafe')
            ->and($preset->apiUrl)->toBe('https://api.typesafe.ai/v1')
            ->and($preset->endpoint)->toBe('/systemone')
            ->and($preset->model)->toBe('jev-latest')
            ->and($preset->apiKey)->toBe('')
            ->and($default->toArray())->toBe($preset->toArray());
    } finally {
        BasePath::set(getcwd() ?: $root);
    }
});

it('prefers application defaults and resolves credentials through an injected source', function () {
    $root = decisionConfigConsumerRoot();
    decisionConfigWriteYaml("{$root}/config/sdm/default.yaml", ['defaultPreset' => 'application']);
    decisionConfigWriteYaml("{$root}/config/sdm/presets/application.yaml", [
        'driver' => 'typesafe',
        'apiUrl' => 'https://sdm.example.test/v1',
        'apiKey' => '${TYPESAFE_API_KEY}',
        'endpoint' => '/decide',
        'model' => 'application-model',
    ]);
    BasePath::set($root);
    $template = new EnvTemplate(new ArraySecretSource('test', ['TYPESAFE_API_KEY' => 'injected-test-key']));

    try {
        $config = DecisionProvider::new(template: $template)->resolveConfig();

        expect($config->apiUrl)->toBe('https://sdm.example.test/v1')
            ->and($config->apiKey)->toBe('injected-test-key')
            ->and($config->model)->toBe('application-model');
    } finally {
        BasePath::set(getcwd() ?: $root);
    }
});

it('supports an explicit preset path dsn and validated overrides', function () {
    $root = decisionConfigConsumerRoot();
    $presets = "{$root}/custom-presets";
    decisionConfigWriteYaml("{$presets}/private.yaml", [
        'driver' => 'compatible',
        'apiUrl' => 'https://private.example.test/v2',
        'apiKey' => 'test-key',
        'endpoint' => '/judge',
        'model' => 'private-model',
    ]);

    $fromPreset = DecisionConfig::fromPreset('private', $presets);
    $fromDsn = DecisionConfig::fromDsn(
        'driver=typesafe,apiUrl=https://api.example.test/v1,apiKey=test-key,endpoint=/systemone,model=jev-test',
    );
    $overridden = $fromPreset->withOverrides(['model' => 'override-model']);

    expect(DecisionConfig::presetNames($presets))->toBe(['private'])
        ->and($fromPreset->driver)->toBe('compatible')
        ->and($fromDsn->model)->toBe('jev-test')
        ->and($overridden->model)->toBe('override-model')
        ->and($fromPreset->model)->toBe('private-model')
        ->and(fn () => DecisionConfig::fromDsn('typesafe'))
        ->toThrow(InvalidArgumentException::class, 'expected comma-separated key=value pairs');
});

it('does not consult defaults when explicit config is provided', function () {
    $root = decisionConfigConsumerRoot();
    decisionConfigWriteYaml("{$root}/config/sdm/default.yaml", ['defaultPreset' => []]);
    BasePath::set($root);
    $explicit = new DecisionConfig(model: 'explicit');

    try {
        expect(DecisionProvider::new($explicit)->resolveConfig())->toBe($explicit);
    } finally {
        BasePath::set(getcwd() ?: $root);
    }
});

it('rejects unknown and invalid fields without exposing credentials', function () {
    $secret = 'sentinel-secret-that-must-not-leak';

    expect(fn () => DecisionConfig::fromArray(['driver' => 'typesafe', 'alias' => 'decision']))
        ->toThrow(InvalidArgumentException::class, 'Unknown DecisionConfig fields: alias');

    try {
        DecisionConfig::fromArray(['apiKey' => $secret, 'model' => []]);
        test()->fail('Expected invalid DecisionConfig field type.');
    } catch (InvalidArgumentException $exception) {
        expect($exception->getMessage())
            ->toContain('Invalid configuration for DecisionConfig')
            ->not->toContain($secret);
    }
});

it('preflights required fields and redacts diagnostic projections', function () {
    $config = new DecisionConfig(
        driver: 'typesafe',
        apiUrl: 'https://api.typesafe.ai/v1',
        apiKey: 'sentinel-test-key',
        endpoint: '/systemone',
        model: '',
    );

    $config->assertUsable('request-model');

    expect($config->toRedactedArray()['apiKey'])->toBe(SensitiveDataRedactor::MASK)
        ->and(fn () => (new DecisionConfig(driver: 'typesafe'))->assertUsable())
        ->toThrow(InvalidArgumentException::class, "field 'apiUrl' is missing or empty")
        ->and(fn () => $config->assertUsable())
        ->toThrow(InvalidArgumentException::class, "field 'model' is missing or empty")
        ->and(fn () => (new DecisionConfig(
            driver: 'typesafe',
            apiUrl: 'file:///tmp/service',
            apiKey: 'test-key',
            endpoint: '/systemone',
            model: 'jev-test',
        ))->assertUsable())->toThrow(InvalidArgumentException::class, 'HTTP(S) URL');
});

function decisionConfigConsumerRoot(): string
{
    $root = sys_get_temp_dir().'/instructor-polyglot-decision-config-'.bin2hex(random_bytes(6));
    mkdir($root, 0777, true);
    file_put_contents("{$root}/composer.json", "{}\n");
    register_shutdown_function(static fn () => decisionConfigDelete($root));

    return $root;
}

/** @param array<string, mixed> $data */
function decisionConfigWriteYaml(string $path, array $data): void
{
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }
    file_put_contents($path, Yaml::dump($data, 8, 2));
}

function decisionConfigDelete(string $path): void
{
    if (! is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $child = "{$path}/{$item}";
        is_dir($child) ? decisionConfigDelete($child) : unlink($child);
    }
    rmdir($path);
}
