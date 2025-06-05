<?php

namespace Cognesy\Polyglot\Embeddings\Drivers\OpenAI;

use Cognesy\Polyglot\Embeddings\Contracts\CanMapUsage;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedResponseAdapter;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Polyglot\Embeddings\EmbeddingsResponse;

class OpenAIResponseAdapter implements EmbedResponseAdapter
{
    public function __construct(
        private readonly CanMapUsage $usageFormat,
    ) {}

    public function fromResponse(array $data): EmbeddingsResponse {
        return new EmbeddingsResponse(
            vectors: array_map(
                callback: fn($item) => new Vector(values: $item['embedding'], id: $item['index']),
                array: $data['data']
            ),
            usage: $this->usageFormat->fromData($data),
        );
    }
}