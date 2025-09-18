## Prioritize Uncertain Examples

When we have a large pool of unlabeled examples that could be used in a prompt, how should we decide which examples to manually label?

Active prompting is a method used to identify the most effective examples for human annotation. The process involves four key steps:

- Uncertainty Estimation: Assess the uncertainty of the LLM's predictions on each possible example
- Selection: Choose the most uncertain examples for human annotation
- Annotation: Have humans label the selected examples
- Inference: Use the newly labeled data to improve the LLM's performance

### Uncertainty Estimation

In this step, we define an unsupervised method to measure the uncertainty of an LLM in answering a given example.

#### Uncertainty Estimation Example

> Let's say we ask an LLM the following query:
> query = "Classify the sentiment of this sentence as positive or negative: I am very excited today."
> and the LLM returns:
> response = "positive"
>
> The goal of uncertainty estimation is to answer: How sure is the LLM in this response?

In order to do this, we query the LLM with the same example k times. Then, we use the k responses to determine how dissimmilar these responses are. Three possible metrics are:

- Disagreement: Ratio of unique responses to total responses.
- Entropy: Measurement based on frequency of each response.
- Variance: Calculation of the spread of numerical responses.

Below is an example of uncertainty estimation for a single input example using the disagreement uncertainty metric.

```python
import instructor
from pydantic import BaseModel
from openai import OpenAI


class Response(BaseModel):
    height: int


client = instructor.from_openai(OpenAI())


def query_llm():
    return client.chat.completions.create(
        model="gpt-4o",
        response_model=Response,
        messages=[
            {
                "role": "user",
                "content": "How tall is the Empire State Building in meters?",
            }
        ],
    )


def calculate_disagreement(responses):
    unique_responses = set(responses)
    h = len(unique_responses)
    return h / k


if __name__ == "__main__":
    k = 5
    responses = [query_llm() for _ in range(k)]  # Query the LLM k times
    for response in responses:
        print(response)
        #> height=443
        #> height=443
        #> height=443
        #> height=443
        #> height=381

    print(
        calculate_disagreement([response.height for response in responses])
    )  # Calculate the uncertainty metric
    #> 0.4
```

This process will then be repeated for all unlabeled examples.

### Selection & Annotation

Once we have a set of examples and their uncertainties, we can select n of them to be annotated by humans. Here, we choose the examples with the highest uncertainties.

### Inference

Now, each time the LLM is prompted, we can include the newly-annotated examples.

### References

1: Active Prompting with Chain-of-Thought for Large Language Models (https://arxiv.org/abs/2302.12246)
2: The Prompt Report: A Systematic Survey of Prompting Techniques (https://arxiv.org/abs/2406.06608)
---
title: 'Prioritize Uncertain Examples'
docname: 'uncertain_examples'
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
