---
title: 'Ditch Vanilla Chain Of Thought'
docname: 'ditch_vanilla_cot'
---

## Overview

How can we improve the effectiveness of Zero-Shot Chain of Thought (CoT) prompts?

Plan and Solve improves the use of Zero-Shot Chain of Thought by adding more detailed instructions to the prompt given to large language models.

**Plan and Solve Process:**

1. **Generate Reasoning**: Prompt the model to explicitly devise a plan for solving a problem before generating intermediate reasoning
2. **Extract Answer**: Extract the final answer from the model's chain of thought

The key improvement is guiding the LLM to pay more attention to calculation and intermediate results to ensure they are correctly performed.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class Reasoning {
    public function __construct(
        public string $chain_of_thought
    ) {}
}

class Response {
    public function __construct(
        public string $correct_answer
    ) {}
}

class PlanAndSolveSolver {
    public function __invoke(string $query): string {
        $reasoning = $this->generateReasoning($query);
        $response = $this->extractAnswer($query, $reasoning);
        return $response->correct_answer;
    }
    
    private function generateReasoning(string $query): Reasoning {
        return (new StructuredOutput)->with(
            messages: [
                [
                    'role' => 'user',
                    'content' => "<user_query>
{$query}
</user_query>

Let's first understand the problem, extract relevant variables and their corresponding numerals, and make a complete plan. Then, let's carry out the plan, calculate intermediate variables (pay attention to correct numerical calculation and commonsense), solve the problem step by step, and show the answer."
                ],
            ],
            responseModel: Reasoning::class,
        )->get();
    }
    
    private function extractAnswer(string $query, Reasoning $reasoning): Response {
        return (new StructuredOutput)->with(
            messages: [
                [
                    'role' => 'user',
                    'content' => "<user_query>
{$query}
</user_query>

Let's first understand the problem, extract relevant variables and their corresponding numerals, and make a complete plan. Then, let's carry out the plan, calculate intermediate variables (pay attention to correct numerical calculation and commonsense), solve the problem step by step, and show the answer.

<reasoning>
{$reasoning->chain_of_thought}
</reasoning>

Therefore the answer (arabic numerals) is"
                ],
            ],
            responseModel: Response::class,
        )->get();
    }
}

$result = (new PlanAndSolveSolver)(
    "In a dance class of 20 students, 20% enrolled in contemporary dance, 25% of the remaining enrolled in jazz dance and the rest enrolled in hip-hop dance. What percentage of the entire students enrolled in hip-hop dance?"
);

dump($result);
?>
```

## References

1. [Plan-and-Solve Prompting: Improving Zero-Shot Chain-of-Thought Reasoning by Large Language Models](https://arxiv.org/pdf/2305.04091)