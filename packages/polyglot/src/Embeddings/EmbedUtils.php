<?php

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Polyglot\Embeddings\Data\Vector;

class EmbedUtils
{
    /**
     * Find the most similar documents to the query
     * @param string $query
     * @param array $documents
     * @param int $topK
     * @return array
     */
    public static function findSimilar(
        EmbeddingsProvider $provider,
        string $query,
        array $documents,
        int $topK = 5
    ) : array {
        // generate embeddings for query and documents (in a single request)
        [$queryVector, $docVectors] = (new Embeddings)
            ->withProvider($provider)
            ->withInputs(array_merge([$query], $documents))
            ->create()
            ->split(1);

        $docVectors = $docVectors->toValuesArray();
        $queryVector = $queryVector->first()?->values()
            ?? throw new \InvalidArgumentException('Query vector not found');

        $matches = self::findTopK($queryVector, $docVectors, $topK);
        return array_map(fn($i) => [
            'content' => $documents[$i],
            'similarity' => $matches[$i],
        ], array_keys($matches));
    }

    /**
     * Find the top K most similar documents to the query vector
     * @param array $queryVector
     * @param array $documentVectors
     * @param int $n
     * @return array
     */
    public static function findTopK(
        array $queryVector,
        array $documentVectors,
        int $n = 5
    ) : array {
        $similarity = [];
        foreach ($documentVectors as $i => $vector) {
            $similarity[$i] = Vector::cosineSimilarity($queryVector, $vector);
        }
        arsort($similarity);
        return array_slice($similarity, 0, $n, true);
    }
}