<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Utils;

use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Embeddings\EmbeddingsProvider;

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
        $embeddings = (new Embeddings)
            ->withProvider($provider)
            ->withInputs(array_merge([$query], $documents))
            ->get();

        [$queryVector, $docVectors] = $embeddings->split(1);
        $queryVector = $queryVector[0] ?? throw new \RuntimeException(
            'The query vector is empty. Please check the embeddings provider.'
        );
        if (count($docVectors) !== count($documents)) {
            throw new \RuntimeException(
                'The number of document vectors does not match the number of documents.'
            );
        }

        $matches = self::findTopK($queryVector, $docVectors, $topK);
        return array_map(fn($i) => [
            'content' => $documents[$i],
            'similarity' => $matches[$i],
        ], array_keys($matches));
    }

    /**
     * Find the top K most similar documents to the query vector
     * @param Vector $queryVector
     * @param Vector[] $documentVectors
     * @param int $n
     * @return array
     */
    public static function findTopK(
        Vector $queryVector,
        array $documentVectors,
        int $n = 5
    ) : array {
        $similarity = [];
        foreach ($documentVectors as $i => $vector) {
            $similarity[$i] = Vector::cosineSimilarity($queryVector->values(), $vector->values());
        }
        arsort($similarity);
        return array_slice($similarity, 0, $n, true);
    }
}