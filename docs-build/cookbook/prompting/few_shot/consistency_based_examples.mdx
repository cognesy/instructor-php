---
title: 'Consistency Based Self Adaptive Prompting (COSP)'
docname: 'consistency_based_examples'
id: '5413'
---
## Overview

COSP is a technique that improves few-shot learning by selecting high-quality examples based on
consistency and confidence of model responses. It identifies examples the model can process reliably.

The process involves:
1. Example Generation: Generate multiple responses per example, collect confidence scores
2. Example Selection: Select examples with low entropy and high repetitiveness

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class ResponseWithConfidence {
    public string $content;
    /** Confidence score between 0 and 1 */
    public float $confidence;
}

class COSPSelector {
    private int $nSamples;

    public function __construct(int $nSamples = 3) {
        $this->nSamples = $nSamples;
    }

    public function generateResponses(string $prompt): array {
        $responses = [];
        for ($i = 0; $i < $this->nSamples; $i++) {
            $responses[] = (new StructuredOutput)->with(
                messages: [['role' => 'user', 'content' => $prompt]],
                responseModel: ResponseWithConfidence::class,
            )->get();
        }
        return $responses;
    }

    public function calculateMetrics(array $responses): array {
        $confidences = array_map(fn($r) => $r->confidence, $responses);
        $entropyScore = $this->entropy($confidences);

        $uniqueResponses = count(array_unique(array_map(fn($r) => $r->content, $responses)));
        $repetitiveness = 1 - ($uniqueResponses / count($responses));

        return ['entropy' => $entropyScore, 'repetitiveness' => $repetitiveness];
    }

    private function entropy(array $values): float {
        $sum = array_sum($values);
        if ($sum == 0) return 0.0;
        $normalized = array_map(fn($v) => $v / $sum, $values);
        $entropy = 0.0;
        foreach ($normalized as $p) {
            if ($p > 0) $entropy -= $p * log($p);
        }
        return $entropy;
    }

    public function selectBestExamples(array $candidates, int $k): array {
        $scored = [];
        foreach ($candidates as $text) {
            $responses = $this->generateResponses("Classify this text: {$text}");
            $metrics = $this->calculateMetrics($responses);
            $score = $metrics['entropy'] - $metrics['repetitiveness'];
            $scored[] = ['text' => $text, 'score' => $score, 'metrics' => $metrics];
        }
        usort($scored, fn($a, $b) => $a['score'] <=> $b['score']);
        return array_slice($scored, 0, $k);
    }
}

$selector = new COSPSelector(nSamples: 3);

$candidates = [
    "The quick brown fox jumps over the lazy dog",
    "Machine learning is a subset of artificial intelligence",
    "Python is a high-level programming language",
];

$bestExamples = $selector->selectBestExamples($candidates, k: 2);

dump($bestExamples);
?>
```

### Benefits

- Improved Consistency: Select examples with low entropy and high repetitiveness
- Automated Selection: No manual example curation needed
- Quality Metrics: Quantifiable measure of example quality

### References

1) Original COSP Paper (https://arxiv.org/abs/2305.14121)
2) Self-Consistency Improves Chain of Thought Reasoning (https://arxiv.org/abs/2203.11171)
