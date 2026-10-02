<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Drivers\Perplexity;

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use InvalidArgumentException;

/**
 * Standard models take a flat list of texts. Contextualized models take
 * documents as lists of chunks; the request inputs are sent as the chunks of
 * one document. Only `base64_int8` output is decodable into vectors.
 */
class PerplexityBodyFormat implements CanMapRequestBody
{
    public const string ENCODING_FORMAT = 'base64_int8';

    public function __construct(
        private readonly EmbeddingsConfig $config
    ) {}

    #[\Override]
    public function toRequestBody(EmbeddingsRequest $request): array
    {
        $model = $request->model() ?: $this->config->model;
        $options = $request->options();
        $encoding = $options['encoding_format'] ?? self::ENCODING_FORMAT;
        if ($encoding !== self::ENCODING_FORMAT) {
            throw new InvalidArgumentException(
                "Perplexity embeddings support only the '".self::ENCODING_FORMAT."' encoding format.",
            );
        }
        $inputs = $request->inputs();

        return array_filter(array_merge($options, [
            'input' => match (true) {
                PerplexityModels::isContextualized($model) => [$inputs],
                default => $inputs,
            },
            'model' => $model,
            'encoding_format' => self::ENCODING_FORMAT,
        ]), static fn (mixed $value): bool => (bool) $value);
    }
}
