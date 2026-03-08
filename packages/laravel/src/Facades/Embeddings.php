<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Facades;

use Cognesy\Instructor\Laravel\Testing\EmbeddingsFake;
use Cognesy\Polyglot\Embeddings\Embeddings as BaseEmbeddings;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\EmbeddingsProvider;
use Cognesy\Polyglot\Embeddings\EmbeddingsRuntime;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for Embeddings
 *
 * @method static \Cognesy\Polyglot\Embeddings\Embeddings connection(string $name)
 * @method static \Cognesy\Polyglot\Embeddings\Embeddings fromConfig(\Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig $config)
 * @method static \Cognesy\Polyglot\Embeddings\Embeddings withRuntime(\Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings $runtime)
 * @method static \Cognesy\Polyglot\Embeddings\Embeddings withInputs(string|array $inputs)
 * @method static \Cognesy\Polyglot\Embeddings\Embeddings withModel(string $model)
 * @method static \Cognesy\Polyglot\Embeddings\Embeddings withOptions(array $options)
 * @method static \Cognesy\Polyglot\Embeddings\Embeddings withRetryPolicy(\Cognesy\Polyglot\Embeddings\Config\EmbeddingsRetryPolicy $retryPolicy)
 * @method static \Cognesy\Polyglot\Embeddings\Embeddings with(string|array $input = [], array $options = [], string $model = '')
 * @method static \Cognesy\Polyglot\Embeddings\Embeddings withRequest(\Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest $request)
 * @method static \Cognesy\Polyglot\Embeddings\PendingEmbeddings create()
 * @method static \Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse get()
 * @method static ?\Cognesy\Polyglot\Embeddings\Data\Vector first()
 * @method static \Cognesy\Polyglot\Embeddings\Data\Vector[] vectors()
 * @method static void registerDriver(string $name, string|callable $driver)
 *
 * @see \Cognesy\Polyglot\Embeddings\Embeddings
 */
class Embeddings extends Facade
{
    /**
     * Create facade instance from explicit typed embeddings config.
     */
    public static function fromConfig(EmbeddingsConfig $config): BaseEmbeddings|EmbeddingsFake
    {
        $root = static::getFacadeRoot();
        if ($root instanceof EmbeddingsFake) {
            return $root->withEmbeddingsConfig($config);
        }
        if ($root instanceof BaseEmbeddings) {
            return new BaseEmbeddings(EmbeddingsRuntime::fromProvider(
                provider: EmbeddingsProvider::fromEmbeddingsConfig($config),
            ));
        }
        throw new \RuntimeException('Embeddings facade root is not initialized.');
    }

    /**
     * Create facade instance from configured Laravel embedding connection name.
     */
    public static function connection(string $name): BaseEmbeddings|EmbeddingsFake
    {
        return static::fromConfig(static::resolveEmbeddingsConfig($name));
    }

    /**
     * Replace the Embeddings instance with a fake for testing.
     */
    public static function fake(array $responses = []): EmbeddingsFake
    {
        $fake = new EmbeddingsFake($responses);
        static::swap($fake);
        return $fake;
    }

    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return BaseEmbeddings::class;
    }

    private static function resolveEmbeddingsConfig(string $name): EmbeddingsConfig
    {
        $raw = config("instructor.embeddings.connections.{$name}", []);
        $connection = is_array($raw) ? $raw : [];
        $driver = (string) ($connection['driver'] ?? $name ?: 'openai');

        return EmbeddingsConfig::fromArray([
            'driver' => $driver,
            'apiUrl' => (string) ($connection['api_url'] ?? ''),
            'apiKey' => (string) ($connection['api_key'] ?? ''),
            'endpoint' => (string) ($connection['endpoint'] ?? '/embeddings'),
            'model' => (string) ($connection['model'] ?? ''),
            'dimensions' => (int) ($connection['dimensions'] ?? 0),
            'maxInputs' => (int) ($connection['max_inputs'] ?? 0),
        ]);
    }
}
