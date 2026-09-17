<?php

declare(strict_types=1);

use Cognesy\Config\BasePath;
use Cognesy\Config\EnvTemplate;
use Cognesy\Config\Secrets\ArraySecretSource;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Decision;
use Cognesy\Polyglot\Decision\DecisionProvider;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Embeddings\EmbeddingsProvider;
use Cognesy\Polyglot\Embeddings\EmbeddingsRuntime;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Polyglot\Inference\Models\ModelCatalog;
use Cognesy\Polyglot\Inference\Models\SupportStatus;
use Symfony\Component\Yaml\Yaml;

it('discovers bundled LLM presets when consumer app has no local config', function () {
    $consumerRoot = polyglotPresetDiscoveryConsumerRoot();
    BasePath::set($consumerRoot);

    try {
        $config = LLMConfig::fromPreset('openai');

        expect($config->driver)->toBe('openai')
            ->and($config->apiUrl)->toBe('https://api.openai.com/v1')
            ->and($config->endpoint)->toBe('/chat/completions')
            ->and($config->model)->not->toBe('');
    } finally {
        BasePath::set(getcwd() ?: $consumerRoot);
    }
});

it('discovers bundled embeddings presets when consumer app has no local config', function () {
    $consumerRoot = polyglotPresetDiscoveryConsumerRoot();
    BasePath::set($consumerRoot);

    try {
        $config = EmbeddingsConfig::fromPreset('openai');

        expect($config->driver)->toBe('openai')
            ->and($config->apiUrl)->toBe('https://api.openai.com/v1')
            ->and($config->endpoint)->toBe('/embeddings')
            ->and($config->model)->toBe('text-embedding-3-small');
    } finally {
        BasePath::set(getcwd() ?: $consumerRoot);
    }
});

it('discovers bundled Decision presets when consumer app has no local config', function () {
    $consumerRoot = polyglotPresetDiscoveryConsumerRoot();
    BasePath::set($consumerRoot);

    try {
        $config = DecisionConfig::fromPreset('typesafe', template: new EnvTemplate(
            new ArraySecretSource('test', ['TYPESAFE_API_KEY' => 'test-key']),
        ));

        expect($config->driver)->toBe('typesafe')
            ->and($config->apiUrl)->toBe('https://api.typesafe.ai/v1')
            ->and($config->endpoint)->toBe('/systemone')
            ->and($config->model)->toBe('jev-latest');
    } finally {
        BasePath::set(getcwd() ?: $consumerRoot);
    }
});

it('uses bundled default preset selectors for no-argument providers', function () {
    $consumerRoot = polyglotPresetDiscoveryConsumerRoot();
    BasePath::set($consumerRoot);

    try {
        $llm = LLMProvider::new()->resolveConfig();
        $embeddings = EmbeddingsProvider::new()->resolveConfig();
        $decision = DecisionProvider::new(template: new EnvTemplate(
            new ArraySecretSource('test', ['TYPESAFE_API_KEY' => 'test-key']),
        ))->resolveConfig();

        expect($llm->driver)->toBe('openai')
            ->and($llm->apiUrl)->toBe('https://api.openai.com/v1')
            ->and($llm->endpoint)->toBe('/chat/completions')
            ->and($llm->model)->not->toBe('')
            ->and($embeddings->driver)->toBe('openai')
            ->and($embeddings->apiUrl)->toBe('https://api.openai.com/v1')
            ->and($embeddings->endpoint)->toBe('/embeddings')
            ->and($embeddings->model)->toBe('text-embedding-3-small')
            ->and($decision->driver)->toBe('typesafe')
            ->and($decision->endpoint)->toBe('/systemone')
            ->and($decision->model)->toBe('jev-latest');
    } finally {
        BasePath::set(getcwd() ?: $consumerRoot);
    }
});

it('executes embeddings with the bundled default provider against a mock client', function () {
    $consumerRoot = polyglotPresetDiscoveryConsumerRoot();
    BasePath::set($consumerRoot);

    try {
        $mock = new MockHttpDriver;
        $mock->on()
            ->post('https://api.openai.com/v1/embeddings')
            ->withJsonSubset(['model' => 'text-embedding-3-small'])
            ->times(1)
            ->replyJson([
                'data' => [['index' => 0, 'embedding' => [0.1, 0.2, 0.3]]],
                'usage' => ['prompt_tokens' => 1],
            ]);
        $http = (new HttpClientBuilder)->withDriver($mock)->create();

        $vectors = Embeddings::fromRuntime(EmbeddingsRuntime::fromProvider(
            provider: EmbeddingsProvider::new(),
            httpClient: $http,
        ))
            ->withInputs('hello')
            ->vectors();

        expect($vectors)->toHaveCount(1)
            ->and($vectors[0]->values())->toBe([0.1, 0.2, 0.3]);
    } finally {
        BasePath::set(getcwd() ?: $consumerRoot);
    }
});

it('prefers application default selectors and presets for no-argument providers', function () {
    $consumerRoot = polyglotPresetDiscoveryConsumerRoot();
    polyglotWriteDefaultPreset($consumerRoot, 'llm', 'application', [
        'driver' => 'openai-compatible',
        'apiUrl' => 'https://llm.example.test/v1',
        'apiKey' => 'test-key',
        'endpoint' => '/responses',
        'model' => 'application-llm',
    ]);
    polyglotWriteDefaultPreset($consumerRoot, 'embed', 'application', [
        'driver' => 'openai',
        'apiUrl' => 'https://embed.example.test/v1',
        'apiKey' => 'test-key',
        'endpoint' => '/embeddings',
        'model' => 'application-embed',
        'dimensions' => 256,
        'maxInputs' => 16,
    ]);
    polyglotWriteDefaultPreset($consumerRoot, 'sdm', 'application', [
        'driver' => 'typesafe',
        'apiUrl' => 'https://decision.example.test/v1',
        'apiKey' => 'test-key',
        'endpoint' => '/systemone',
        'model' => 'application-decision',
    ]);
    BasePath::set($consumerRoot);

    try {
        $llm = LLMProvider::new()->resolveConfig();
        $embeddings = EmbeddingsProvider::new()->resolveConfig();
        $decision = DecisionProvider::new()->resolveConfig();

        expect($llm->apiUrl)->toBe('https://llm.example.test/v1')
            ->and($llm->model)->toBe('application-llm')
            ->and($embeddings->apiUrl)->toBe('https://embed.example.test/v1')
            ->and($embeddings->model)->toBe('application-embed')
            ->and($embeddings->dimensions)->toBe(256)
            ->and($decision->apiUrl)->toBe('https://decision.example.test/v1')
            ->and($decision->model)->toBe('application-decision');
    } finally {
        BasePath::set(getcwd() ?: $consumerRoot);
    }
});

it('does not consult default selectors when an explicit config is provided', function () {
    $consumerRoot = polyglotPresetDiscoveryConsumerRoot();
    polyglotWriteYaml($consumerRoot.'/config/llm/default.yaml', ['defaultPreset' => []]);
    polyglotWriteYaml($consumerRoot.'/config/embed/default.yaml', ['defaultPreset' => []]);
    polyglotWriteYaml($consumerRoot.'/config/sdm/default.yaml', ['defaultPreset' => []]);
    BasePath::set($consumerRoot);

    try {
        $llm = new LLMConfig(model: 'explicit-llm');
        $embeddings = new EmbeddingsConfig(model: 'explicit-embed');
        $decision = new DecisionConfig(model: 'explicit-decision');

        expect(LLMProvider::new($llm)->resolveConfig())->toBe($llm)
            ->and(EmbeddingsProvider::new($embeddings)->resolveConfig())->toBe($embeddings)
            ->and(DecisionProvider::new($decision)->resolveConfig())->toBe($decision);
    } finally {
        BasePath::set(getcwd() ?: $consumerRoot);
    }
});

it('fails clearly when an application default selector is malformed', function () {
    $consumerRoot = polyglotPresetDiscoveryConsumerRoot();
    polyglotWriteYaml($consumerRoot.'/config/llm/default.yaml', ['defaultPreset' => []]);
    BasePath::set($consumerRoot);

    try {
        expect(fn () => LLMProvider::new())
            ->toThrow(InvalidArgumentException::class, "Invalid LLM default preset selector: expected non-empty string at 'defaultPreset'.");
    } finally {
        BasePath::set(getcwd() ?: $consumerRoot);
    }
});

it('fails clearly when the selected application preset is unavailable', function () {
    $consumerRoot = polyglotPresetDiscoveryConsumerRoot();
    polyglotWriteYaml($consumerRoot.'/config/embed/default.yaml', ['defaultPreset' => 'unavailable']);
    BasePath::set($consumerRoot);

    try {
        expect(fn () => EmbeddingsProvider::new())
            ->toThrow(InvalidArgumentException::class, "Config file 'unavailable.yaml' was not found");
    } finally {
        BasePath::set(getcwd() ?: $consumerRoot);
    }
});

it('does not expose credentials when a selected default preset is invalid', function () {
    $consumerRoot = polyglotPresetDiscoveryConsumerRoot();
    polyglotWriteDefaultPreset($consumerRoot, 'embed', 'invalid', [
        'driver' => 'openai',
        'apiUrl' => 'https://embed.example.test/v1',
        'apiKey' => 'must-not-appear-in-errors',
        'endpoint' => '/embeddings',
        'model' => 'application-embed',
        'dimensions' => [],
        'maxInputs' => 16,
    ]);
    BasePath::set($consumerRoot);

    try {
        try {
            EmbeddingsProvider::new();
            test()->fail('Expected invalid default embeddings preset to fail.');
        } catch (InvalidArgumentException $exception) {
            expect($exception->getMessage())
                ->toContain('Invalid dimensions value')
                ->not->toContain('must-not-appear-in-errors');
        }
    } finally {
        BasePath::set(getcwd() ?: $consumerRoot);
    }
});

it('builds inference from bundled openai preset without app-local config', function () {
    $consumerRoot = polyglotPresetDiscoveryConsumerRoot();
    BasePath::set($consumerRoot);

    try {
        expect(Inference::using('openai'))->toBeInstanceOf(Inference::class);
    } finally {
        BasePath::set(getcwd() ?: $consumerRoot);
    }
});

it('builds Decision from the bundled TypeSafe provider without app-local config', function () {
    $consumerRoot = polyglotPresetDiscoveryConsumerRoot();
    BasePath::set($consumerRoot);

    try {
        $provider = DecisionProvider::new(template: new EnvTemplate(
            new ArraySecretSource('test', ['TYPESAFE_API_KEY' => 'test-key']),
        ));

        expect(Decision::fromProvider($provider))->toBeInstanceOf(Decision::class);
    } finally {
        BasePath::set(getcwd() ?: $consumerRoot);
    }
});

it('discovers Decision config from aggregate and split Composer layouts', function (string $relativePath) {
    $consumerRoot = polyglotPresetDiscoveryConsumerRoot();
    $configRoot = $consumerRoot.'/'.$relativePath.'/resources/config/sdm';
    polyglotWriteYaml($configRoot.'/default.yaml', ['defaultPreset' => 'installed']);
    polyglotWriteYaml($configRoot.'/presets/installed.yaml', [
        'driver' => 'typesafe',
        'apiUrl' => 'https://installed.example.test/v1',
        'apiKey' => '${TYPESAFE_API_KEY}',
        'endpoint' => '/systemone',
        'model' => 'installed-model',
    ]);
    BasePath::set($consumerRoot);

    try {
        $config = DecisionConfig::fromDefaults(new EnvTemplate(
            new ArraySecretSource('test', ['TYPESAFE_API_KEY' => 'test-key']),
        ));

        expect($config->apiUrl)->toBe('https://installed.example.test/v1')
            ->and($config->model)->toBe('installed-model');
    } finally {
        BasePath::set(getcwd() ?: $consumerRoot);
    }
})->with([
    'aggregate package' => 'vendor/cognesy/instructor-php/packages/polyglot',
    'split package' => 'vendor/cognesy/instructor-polyglot',
]);

it('discovers an application model catalog before bundled records', function () {
    $consumerRoot = polyglotPresetDiscoveryConsumerRoot();
    $config = $consumerRoot.'/config/llm';
    mkdir($config.'/models/openai', 0777, true);
    file_put_contents($config.'/models/openai/gpt-test.yaml', Yaml::dump([
        'schemaVersion' => 1,
        'version' => 'project-v1',
        'profile' => [
            'driver' => 'openai',
            'model' => 'gpt-test',
            'status' => 'supported',
            'limits' => ['contextWindow' => 64000],
            'source' => 'project',
        ],
    ], 12, 2));
    BasePath::set($consumerRoot);

    try {
        $catalog = ModelCatalog::discover();

        expect($catalog->find('openai', 'gpt-test')->limits->contextWindow)->toBe(64000)
            ->and($catalog->find('qwen', 'qwen3.8-max')->status)->toBe(SupportStatus::Supported);
    } finally {
        BasePath::set(getcwd() ?: $consumerRoot);
    }
});

it('memoizes model catalog discovery per application base path', function () {
    $firstRoot = polyglotPresetDiscoveryConsumerRoot();
    $secondRoot = polyglotPresetDiscoveryConsumerRoot();
    BasePath::set($firstRoot);

    try {
        $catalog = ModelCatalog::discover();
        foreach (range(1, 1000) as $_) {
            expect(ModelCatalog::discover())->toBe($catalog);
        }

        BasePath::set($secondRoot);
        $secondCatalog = ModelCatalog::discover();

        expect($secondCatalog)->not->toBe($catalog)
            ->and(ModelCatalog::discover())->toBe($secondCatalog);
    } finally {
        BasePath::set(getcwd() ?: $firstRoot);
    }
});

function polyglotPresetDiscoveryConsumerRoot(): string
{
    $dir = sys_get_temp_dir().'/instructor-polyglot-preset-discovery-'.bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);
    file_put_contents($dir.'/composer.json', "{}\n");

    register_shutdown_function(static function () use ($dir): void {
        polyglotPresetDiscoveryDeleteDir($dir);
    });

    return $dir;
}

/** @param array<string, mixed> $preset */
function polyglotWriteDefaultPreset(string $root, string $group, string $name, array $preset): void
{
    polyglotWriteYaml("{$root}/config/{$group}/default.yaml", ['defaultPreset' => $name]);
    polyglotWriteYaml("{$root}/config/{$group}/presets/{$name}.yaml", $preset);
}

/** @param array<string, mixed> $data */
function polyglotWriteYaml(string $path, array $data): void
{
    $directory = dirname($path);
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
    file_put_contents($path, Yaml::dump($data, 12, 2));
}

function polyglotPresetDiscoveryDeleteDir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir.'/'.$item;
        if (is_dir($path)) {
            polyglotPresetDiscoveryDeleteDir($path);

            continue;
        }

        unlink($path);
    }

    rmdir($dir);
}
