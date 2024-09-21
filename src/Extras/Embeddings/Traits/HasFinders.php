<?php
namespace Cognesy\Instructor\Extras\Embeddings\Traits;

use Cognesy\Instructor\Extras\Embeddings\Vector;

trait HasFinders
{
    public function findSimilar(string $query, array $documents, int $topK = 5) : array {
        // generate embeddings for query and documents (in a single request)
        [$queryVector, $docVectors] = $this->create(array_merge([$query], $documents))->split(1);

        $docVectors = $docVectors->toValuesArray();
        $queryVector = $queryVector->first()->values()
            ?? throw new \InvalidArgumentException('Query vector not found');

        $matches = self::findTopK($queryVector, $docVectors, $topK);
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

//if ($this->clientType !== ClientType::Jina && $this->model === 'jina-colbert-v2') {
//    $docVectors = $this->make($documents, ['options' => ['input_type' => 'document']]);
//    $queryVector = $this->make($query, ['options' => ['input_type' => 'query']]);
//} else {
//    $docVectors = $this->make($documents);
//    $queryVector = $this->make($query);
//}
