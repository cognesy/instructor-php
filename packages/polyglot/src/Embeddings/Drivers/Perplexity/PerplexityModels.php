<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Drivers\Perplexity;

/** Contextualized models embed chunks of one document against a separate endpoint. */
final class PerplexityModels
{
    public const string CONTEXTUALIZED_PREFIX = 'pplx-embed-context-';
    public const string CONTEXTUALIZED_ENDPOINT = '/contextualizedembeddings';

    public static function isContextualized(string $model): bool
    {
        return str_starts_with($model, self::CONTEXTUALIZED_PREFIX);
    }
}
