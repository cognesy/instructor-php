<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Facades;

use Cognesy\Instructor\Laravel\Testing\EmbeddingsFake;
use Cognesy\Polyglot\Embeddings\Embeddings as BaseEmbeddings;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for Embeddings
 *
 * @method static \Cognesy\Polyglot\Embeddings\Embeddings using(string $preset)
 * @method static \Cognesy\Polyglot\Embeddings\Embeddings withInputs(string|array $inputs)
 * @method static \Cognesy\Polyglot\Embeddings\Embeddings withModel(string $model)
 * @method static \Cognesy\Polyglot\Embeddings\Embeddings withOptions(array $options)
 * @method static \Cognesy\Polyglot\Embeddings\Embeddings withHttpClient(\Cognesy\Http\HttpClient $httpClient)
 * @method static \Cognesy\Polyglot\Embeddings\Embeddings withConfig(\Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig $config)
 * @method static \Cognesy\Polyglot\Embeddings\Embeddings withConfigOverrides(array $overrides)
 * @method static \Cognesy\Polyglot\Embeddings\Embeddings withDsn(string $dsn)
 * @method static \Cognesy\Polyglot\Embeddings\Embeddings withDebugPreset(?string $preset)
 * @method static \Cognesy\Polyglot\Embeddings\Embeddings wiretap(callable $callback)
 * @method static \Cognesy\Polyglot\Embeddings\Embeddings onEvent(string $eventClass, callable $callback)
 * @method static \Cognesy\Polyglot\Embeddings\PendingEmbeddings create()
 * @method static \Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse get()
 * @method static array first()
 * @method static array all()
 * @method static void registerDriver(string $name, string|callable $driver)
 *
 * @see \Cognesy\Polyglot\Embeddings\Embeddings
 */
class Embeddings extends Facade
{
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
}
