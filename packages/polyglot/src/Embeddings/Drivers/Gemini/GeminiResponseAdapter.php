<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Drivers\Gemini;

use Cognesy\Polyglot\Embeddings\Contracts\CanMapUsage;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedResponseAdapter;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;
use Cognesy\Polyglot\Embeddings\Data\Vector;

class GeminiResponseAdapter implements EmbedResponseAdapter
{
    public function __construct(
        private readonly CanMapUsage $usageFormat,
    ) {}

    public function fromResponse(array $data): EmbeddingsResponse {
        $vectors = [];
        foreach ($data['embeddings'] as $key => $item) {
            $vectors[] = new Vector(values: $item['values'], id: $key);
        }

        return new EmbeddingsResponse(
            vectors: $vectors,
            usage: $this->usageFormat->fromData($data),
        );
    }
}