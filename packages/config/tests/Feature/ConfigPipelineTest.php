<?php declare(strict_types=1);

namespace Cognesy\Config\Tests\Feature;

use Cognesy\Config\ConfigBootstrap;
use Cognesy\Config\ConfigCacheCompiler;
use Cognesy\Config\ConfigFileSet;
use Cognesy\Config\ConfigValidator;
use InvalidArgumentException;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

it('bootstraps an explicit split config file set into one graph', function () {
    $tmp = configPipelineTmpDir();
    $default = configPipelineWriteFile(
        $tmp . '/config/polyglot/llm/default.yaml',
        "driver: openai\nmodel: gpt-4o-mini\n",
    );
    $preset = configPipelineWriteFile(
        $tmp . '/config/polyglot/llm/presets/openai.yaml',
        "driver: openai\nmodel: gpt-5\n",
    );

    $graph = (new ConfigBootstrap())->bootstrap(ConfigFileSet::fromFiles($preset, $default));

    expect($graph)->toBe([
        'polyglot' => [
            'llm' => [
                'default' => [
                    'driver' => 'openai',
                    'model' => 'gpt-4o-mini',
                ],
                'presets' => [
                    'openai' => [
                        'driver' => 'openai',
                        'model' => 'gpt-5',
                    ],
                ],
            ],
        ],
    ]);
});

it('orders file sets deterministically and hashes file contents', function () {
    $tmp = configPipelineTmpDir();
    $first = configPipelineWriteFile($tmp . '/config/polyglot/llm/presets/z.yaml', "driver: zed\n");
    $second = configPipelineWriteFile($tmp . '/config/polyglot/llm/default.yaml', "driver: openai\n");

    $left = ConfigFileSet::fromFiles($first, $second);
    $right = ConfigFileSet::fromFiles($second, $first);

    expect($left->keys())->toBe([
        'polyglot.llm.default',
        'polyglot.llm.presets.z',
    ])->and($left->filesHash())->toBe($right->filesHash());
});

it('fails when two files resolve to the same config key', function () {
    $tmp = configPipelineTmpDir();
    $yaml = configPipelineWriteFile($tmp . '/config/polyglot/llm/default.yaml', "driver: openai\n");
    $yml = configPipelineWriteFile($tmp . '/config/polyglot/llm/default.yml', "driver: anthropic\n");

    expect(fn() => (new ConfigBootstrap())->bootstrap(ConfigFileSet::fromFiles($yaml, $yml)))
        ->toThrow(InvalidArgumentException::class, 'Duplicate config key derived from file set');
});

it('validates merged config graphs with a lightweight symfony schema', function () {
    $validator = new ConfigValidator(new class implements ConfigurationInterface {
        public function getConfigTreeBuilder(): TreeBuilder
        {
            $treeBuilder = new TreeBuilder('root');
            $root = $treeBuilder->getRootNode();

            $root
                ->children()
                    ->arrayNode('polyglot')
                        ->children()
                            ->arrayNode('llm')
                                ->children()
                                    ->arrayNode('default')
                                        ->children()
                                            ->scalarNode('driver')->isRequired()->end()
                                            ->scalarNode('model')->isRequired()->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end();

            return $treeBuilder;
        }
    });

    $valid = $validator->validate([
        'polyglot' => [
            'llm' => [
                'default' => [
                    'driver' => 'openai',
                    'model' => 'gpt-4o-mini',
                ],
            ],
        ],
    ]);

    expect($valid['polyglot']['llm']['default']['driver'])->toBe('openai');

    expect(fn() => $validator->validate([
        'polyglot' => [
            'llm' => [
                'default' => [
                    'driver' => 'openai',
                    'unknown' => 'value',
                ],
            ],
        ],
    ]))->toThrow(InvalidConfigurationException::class);
});

it('compiles a deterministic cache artifact with metadata', function () {
    $tmp = configPipelineTmpDir();
    $file = configPipelineWriteFile(
        $tmp . '/config/http-client/http/default.yaml',
        "driver: curl\nrequest_timeout: 30\n",
    );
    $cache = $tmp . '/var/cache/config.php';
    $fileSet = ConfigFileSet::fromFiles($file);
    $graph = (new ConfigBootstrap())->bootstrap($fileSet);

    (new ConfigCacheCompiler())->compile(
        cachePath: $cache,
        fileSet: $fileSet,
        config: $graph,
        env: ['APP_ENV' => 'test'],
        schemaVersion: 3,
        generatedAt: '2026-03-10T12:00:00+00:00',
    );

    /** @var array<string, mixed> $payload */
    $payload = require $cache;

    expect($payload['_meta'])->toBe([
        'schema_version' => 3,
        'files_hash' => $fileSet->filesHash(),
        'env_hash' => hash('sha256', json_encode(['APP_ENV' => 'test'], JSON_THROW_ON_ERROR)),
        'generated_at' => '2026-03-10T12:00:00+00:00',
        'file_count' => 1,
        'files' => ['http-client.http.default'],
    ])->and($payload['config'])->toBe($graph);
});

function configPipelineTmpDir(): string
{
    $dir = sys_get_temp_dir() . '/instructor-config-pipeline-' . bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);
    register_shutdown_function(static function () use ($dir): void {
        configPipelineDeleteDir($dir);
    });

    return $dir;
}

function configPipelineWriteFile(string $path, string $content): string
{
    $directory = dirname($path);
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    file_put_contents($path, $content);

    return $path;
}

function configPipelineDeleteDir(string $dir): void
{
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
            configPipelineDeleteDir($path);
            continue;
        }

        unlink($path);
    }

    rmdir($dir);
}
