<?php declare(strict_types=1);

namespace Cognesy\Retrieval\Vectorization;

use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Retrieval\Data\VectorDocument;
use Cognesy\Retrieval\Data\VectorDocuments;
use Cognesy\Retrieval\Indexing\Data\TextDocument;
use InvalidArgumentException;

final readonly class Vectorizer
{
    /** @param array<string, mixed> $options */
    public function __construct(
        private CanCreateEmbeddings $embeddings,
        private string $embeddingSpace,
        private string $model = '',
        private array $options = [],
    ) {
        if ($embeddingSpace === '') {
            throw new InvalidArgumentException('Embedding space cannot be empty');
        }
    }

    /** @param list<TextDocument> $documents */
    public function vectorize(array $documents): VectorDocuments
    {
        if ($documents === []) {
            return new VectorDocuments;
        }
        $response = $this->embeddings->create(new EmbeddingsRequest(
            input: array_map(static fn (TextDocument $document): string => $document->content, $documents),
            options: $this->options,
            model: $this->model,
        ))->get();
        $vectors = $this->correlate(array_values($response->vectors()), count($documents));
        $records = [];
        foreach ($documents as $index => $document) {
            $records[] = new VectorDocument(
                id: $document->id,
                vector: $vectors[$index],
                metadata: [
                    ...$document->metadata,
                    '_source_id' => $document->sourceIdentity(),
                    '_source_version' => $document->sourceVersion,
                ],
                content: $document->content,
                embeddingSpace: $this->embeddingSpace,
            );
        }

        return new VectorDocuments($records);
    }

    public function vectorizeText(string $text): Vector
    {
        if (trim($text) === '') {
            throw new InvalidArgumentException('Text to vectorize cannot be empty');
        }
        $response = $this->embeddings->create(new EmbeddingsRequest(
            input: [$text],
            options: $this->options,
            model: $this->model,
        ))->get();

        return $this->correlate(array_values($response->vectors()), 1)[0];
    }

    /**
     * @param  list<Vector>  $vectors
     * @return list<Vector>
     */
    private function correlate(array $vectors, int $inputCount): array
    {
        if (count($vectors) !== $inputCount) {
            throw new InvalidArgumentException('Embedding response cardinality does not match input count');
        }
        $indexed = [];
        foreach ($vectors as $vector) {
            $id = $vector->id();
            if (! is_int($id) || $id < 0 || $id >= $inputCount || isset($indexed[$id])) {
                throw new InvalidArgumentException('Embedding response contains an invalid or duplicate input index');
            }
            $indexed[$id] = $vector;
        }
        ksort($indexed);
        if (array_keys($indexed) !== range(0, $inputCount - 1)) {
            throw new InvalidArgumentException('Embedding response is missing an input index');
        }

        return array_values($indexed);
    }
}
