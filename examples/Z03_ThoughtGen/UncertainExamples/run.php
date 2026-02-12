---
title: 'Prioritize Uncertain Examples'
docname: 'uncertain_examples'
id: '52cc'
---
## Overview

When we have a large pool of unlabeled examples that could be used in a prompt, how should we decide which examples to manually label?

Active prompting identifies effective examples for human annotation using:
- Uncertainty Estimation: Measure uncertainty on each example.
- Selection: Choose the most uncertain examples for human labeling.
- Annotation: Humans label selected examples.
- Inference: Use newly labeled data to improve prompts.

## Uncertainty Estimation (Disagreement)

Query the same example k times and measure disagreement: unique responses / total responses.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\Extras\Scalar\Scalar;
use Cognesy\Instructor\StructuredOutput;

class EstimateUncertainty {
    public function __invoke(int $k = 5) : float {
        $values = [];
        for ($i = 0; $i < $k; $i++) {
            $values[] = $this->queryHeight();
        }
        return $this->disagreement($values);
    }

    private function queryHeight() : int {
        return (new StructuredOutput)->with(
            messages: [['role' => 'user', 'content' => 'How tall is the Empire State Building in meters?']],
            responseModel: Scalar::integer('height'),
        )->get();
    }

    private function disagreement(array $responses) : float {
        $n = count($responses);
        if ($n === 0) return 0.0;
        return count(array_unique($responses)) / $n;
    }
}

$score = (new EstimateUncertainty)(k: 5);
dump($score);
?>
```

### Selection & Annotation

Select the top-n most uncertain unlabeled examples for human annotation.

### Inference

Use newly annotated examples as few-shot context during inference.

### References

1) Active Prompting with Chain-of-Thought for Large Language Models (https://arxiv.org/abs/2302.12246)
2) The Prompt Report: A Systematic Survey of Prompting Techniques (https://arxiv.org/abs/2406.06608)
