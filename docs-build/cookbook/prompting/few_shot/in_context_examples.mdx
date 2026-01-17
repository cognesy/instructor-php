---
title: 'Generate In-Context Examples'
docname: 'in_context_examples'
---

## Overview

How can we generate examples for our prompt?

Self-Generated In-Context Learning (SG-ICL) is a technique which uses an LLM
to generate examples to be used during the task. This allows for in-context
learning, where examples of the task are provided in the prompt.

We can implement SG-ICL using Instructor as seen below.


## Example

```php
\<\?php
require 'examples/boot.php';

use Cognesy\Instructor\Extras\Example\Example;
use Cognesy\Instructor\Extras\Scalar\Scalar;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;

enum ReviewSentiment : string {
    case Positive = 'positive';
    case Negative = 'negative';
}

class GeneratedReview {
    public string $review;
    public ReviewSentiment $sentiment;
}


class PredictSentiment {
    private int $n = 4;

    public function __invoke(string $review) : ReviewSentiment {
        return (new StructuredOutput)->with(
            messages: [
                ['role' => 'user', 'content' => "Review: {$review}"],
            ],
            responseModel: Scalar::enum(ReviewSentiment::class),
            examples: $this->generateExamples($review),
        )->get();
    }

    private function generate(string $inputReview, ReviewSentiment $sentiment) : array {
        return (new StructuredOutput)->with(
            messages: [
                ['role' => 'user', 'content' => "Generate {$this->n} various {$sentiment->value} reviews based on the input review:\n{$inputReview}"],
                ['role' => 'user', 'content' => "Generated review:"],
            ],
            responseModel: Sequence::of(GeneratedReview::class),
        )->get()->toArray();
    }

    private function generateExamples(string $inputReview) : array {
        $examples = [];
        foreach ([ReviewSentiment::Positive, ReviewSentiment::Negative] as $sentiment) {
            $samples = $this->generate($inputReview, $sentiment);
            foreach ($samples as $sample) {
                $examples[] = Example::fromData($sample->review, $sample->sentiment->value);
            }
        }
        return $examples;
    }
}

$predictSentiment = (new PredictSentiment)('This movie has been very impressive, even considering I lost half of the plot.');

dump($predictSentiment);
?>
```

## References

 1. [Self-Generated In-Context Learning: Leveraging Auto-regressive Language Models as a Demonstration Generator](https://arxiv.org/abs/2206.08082)
 2. [The Prompt Report: A Systematic Survey of Prompting Techniques](https://arxiv.org/abs/2406.06608)
