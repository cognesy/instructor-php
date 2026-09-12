<?php declare(strict_types=1);

use Cognesy\Config\BasePath;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\Models\ModelCatalog;
use Cognesy\Polyglot\Inference\Models\SupportStatus;

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

it('builds inference from bundled openai preset without app-local config', function () {
    $consumerRoot = polyglotPresetDiscoveryConsumerRoot();
    BasePath::set($consumerRoot);

    try {
        expect(Inference::using('openai'))->toBeInstanceOf(Inference::class);
    } finally {
        BasePath::set(getcwd() ?: $consumerRoot);
    }
});

it('discovers an application model catalog before bundled records', function () {
    $consumerRoot = polyglotPresetDiscoveryConsumerRoot();
    $config = $consumerRoot . '/config/llm';
    mkdir($config . '/models/openai', 0777, true);
    file_put_contents($config . '/models/openai/gpt-test.yaml', \Symfony\Component\Yaml\Yaml::dump([
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

function polyglotPresetDiscoveryConsumerRoot(): string {
    $dir = sys_get_temp_dir() . '/instructor-polyglot-preset-discovery-' . bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/composer.json', "{}\n");

    register_shutdown_function(static function () use ($dir): void {
        polyglotPresetDiscoveryDeleteDir($dir);
    });

    return $dir;
}

function polyglotPresetDiscoveryDeleteDir(string $dir): void {
    if (!is_dir($dir)) {
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

        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            polyglotPresetDiscoveryDeleteDir($path);
            continue;
        }

        unlink($path);
    }

    rmdir($dir);
}
