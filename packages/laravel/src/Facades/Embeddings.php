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
 * @method static \Cognesy\Polyglot\Embeddings\Embeddings fromDsn(string $dsn)
 * @method static \Cognesy\Polyglot\Embeddings\Embeddings fromRuntime(\Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings $runtime)
 * @method static \Cognesy\Polyglot\Embeddings\Embeddings withRuntime(\Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings $runtime)
 * @method static \Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings runtime()
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
