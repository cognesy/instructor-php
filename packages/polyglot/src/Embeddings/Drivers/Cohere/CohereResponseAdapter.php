<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Drivers\Cohere;

use Cognesy\Polyglot\Embeddings\Contracts\CanMapUsage;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedResponseAdapter;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;
use Cognesy\Polyglot\Embeddings\Data\Vector;

class CohereResponseAdapter implements EmbedResponseAdapter
{
    public function __construct(
        private readonly CanMapUsage $usageFormat,
    ) {}

    #[\Override]
    public function fromResponse(array $data): EmbeddingsResponse {
        $vectors = [];
        foreach ($data['embeddings']['float'] as $key => $item) {
            $vectors[] = new Vector(values: $item, id: $key);
        }

        return new EmbeddingsResponse(
            vectors: $vectors,
            usage: $this->usageFormat->fromData($data),
        );
    }
}