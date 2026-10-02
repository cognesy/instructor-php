<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Drivers\Perplexity;

use Cognesy\Polyglot\Embeddings\Contracts\CanMapUsage;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedResponseAdapter;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use RuntimeException;

/**
 * Decodes base64 signed-int8 embeddings into vectors. Values are unnormalized,
 * so compare them by cosine similarity. Contextualized responses nest chunk
 * embeddings per document; they are flattened in document and chunk order.
 */
class PerplexityResponseAdapter implements EmbedResponseAdapter
{
    public function __construct(
        private readonly CanMapUsage $usageFormat,
    ) {}

    #[\Override]
    public function fromResponse(array $data): EmbeddingsResponse
    {
        $items = $this->embeddingItems($data['data'] ?? null);

        return new EmbeddingsResponse(
            vectors: array_map(
                fn (array $item, int $position): Vector => new Vector(
                    values: $this->decode($item['embedding'] ?? null),
                    id: $position,
                ),
                $items,
                array_keys($items),
            ),
            usage: $this->usageFormat->fromData($data),
        );
    }

    /** @return list<array<string, mixed>> */
    private function embeddingItems(mixed $data): array
    {
        if (! is_array($data)) {
            throw new RuntimeException('Perplexity embeddings response is missing data.');
        }
        $items = [];
        foreach ($this->sortedByIndex($data) as $entry) {
            $chunks = match (true) {
                is_array($entry['data'] ?? null) => $this->sortedByIndex($entry['data']),
                default => [$entry],
            };
            array_push($items, ...$chunks);
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function sortedByIndex(array $entries): array
    {
        $entries = array_values(array_filter($entries, 'is_array'));
        usort($entries, static fn (array $a, array $b): int => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

        return $entries;
    }

    /** @return list<float> */
    private function decode(mixed $embedding): array
    {
        $bytes = match (true) {
            is_string($embedding) => base64_decode($embedding, true),
            default => false,
        };
        $values = match (true) {
            $bytes === false || $bytes === '' => false,
            default => unpack('c*', $bytes),
        };
        if ($values === false) {
            throw new RuntimeException('Perplexity embedding must be a non-empty base64 int8 string.');
        }

        return array_values(array_map(static fn (mixed $value): float => (float) $value, $values));
    }
}
