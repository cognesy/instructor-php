<?php
namespace Cognesy\Instructor\Extras\Embeddings\Traits;

use Cognesy\Instructor\Extras\Embeddings\Vector;

trait HasFinders
{
    public function findSimilar(string $query, array $documents, int $k = 5) : array {
        $docVectors = $this->make($documents);
        $queryVector = $this->make($query);

        //if ($this->clientType !== ClientType::Jina && $this->model === 'jina-colbert-v2') {
        //    $docVectors = $this->make($documents, ['options' => ['input_type' => 'document']]);
        //    $queryVector = $this->make($query, ['options' => ['input_type' => 'query']]);
        //} else {
        //    $docVectors = $this->make($documents);
        //    $queryVector = $this->make($query);
        //}

        $matches = self::findTopK($queryVector[0], $docVectors, $k);
        return array_map(fn($i) => [
            'content' => $documents[$i],
            'similarity' => $matches[$i],
        ], array_keys($matches));
    }

    public static function findTopK(array $queryVector, array $documentVectors, int $n = 5) : array {
        $similarity = [];
        foreach ($documentVectors as $i => $vector) {
            $similarity[$i] = Vector::cosineSimilarity($queryVector, $vector);
        }
        arsort($similarity);
        return array_slice($similarity, 0, $n, true);
    }
}
