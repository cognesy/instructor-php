---
title: 'Select Effective Examples'
docname: 'select_effective_samples'
---

## Overview

Select effective in-context examples by choosing those semantically closest to the query using KNN
(k-Nearest Neighbors) with embeddings.

Steps:
1. Embed the candidate examples
2. Embed the query to answer
3. Find the k examples closest to the query
4. Use chosen examples as context for the LLM

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Embeddings\Embeddings;

class Answer {
    public string $answer;
}

class KNNExampleSelector {
    private Embeddings $embeddings;

    public function __construct() {
        $this->embeddings = new Embeddings();
    }

    public function cosineSimilarity(array $a, array $b): float {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }
        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    public function embed(array $texts): array {
        $result = $this->embeddings->create($texts)->first()->embedding;
        return $result;
    }

    public function embedAll(array $texts): array {
        $results = [];
        foreach ($texts as $text) {
            $results[] = $this->embed([$text]);
        }
        return $results;
    }

    public function selectKNearest(array $examples, string $query, int $k): array {
        $questions = array_column($examples, 'question');
        $exampleEmbeddings = $this->embedAll($questions);
        $queryEmbedding = $this->embed([$query]);

        $scored = [];
        foreach ($examples as $i => $example) {
            $similarity = $this->cosineSimilarity($exampleEmbeddings[$i], $queryEmbedding);
            $scored[] = ['example' => $example, 'similarity' => $similarity];
        }

        usort($scored, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
        return array_slice($scored, 0, $k);
    }

    public function generateWithExamples(array $selectedExamples, string $query): Answer {
        $context = "";
        foreach ($selectedExamples as $item) {
            $ex = $item['example'];
            $context .= "<example>\n<question>{$ex['question']}</question>\n<answer>{$ex['answer']}</answer>\n</example>\n";
        }

        $prompt = "Respond to the query using the examples as guidance.\n\n{$context}\n<query>{$query}</query>";

        return (new StructuredOutput)->with(
            messages: [['role' => 'user', 'content' => $prompt]],
            responseModel: Answer::class,
        )->get();
    }
}

$selector = new KNNExampleSelector();

$examples = [
    ['question' => 'What is the capital of France?', 'answer' => 'Paris'],
    ['question' => 'Who wrote Romeo and Juliet?', 'answer' => 'Shakespeare'],
    ['question' => 'What is the capital of Germany?', 'answer' => 'Berlin'],
];

$query = 'What is the capital of Italy?';

$kClosest = $selector->selectKNearest($examples, $query, k: 2);
dump($kClosest);

$response = $selector->generateWithExamples($kClosest, $query);
dump($response);
?>
```

## References

1) What Makes Good In-Context Examples for GPT-3? (https://arxiv.org/abs/2101.06804)
2) The Prompt Report: A Systematic Survey of Prompting Techniques (https://arxiv.org/abs/2406.06608)
