---
title: 'Structure The Reasoning'
docname: 'structure_reasoning'
---

## Overview

By getting language models to output their reasoning as a structured table, we can improve their reasoning capabilities and the quality of their outputs. This is known as Tabular Chain Of Thought (Tab-CoT).

We can implement this using Instructor with a response model ensuring we get exactly the data that we want. Each row in the table is represented as a `ReasoningStep` object.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class ReasoningStep {
    public int $step;
    public string $subquestion;
    public string $procedure;
    public string $result;
}

class Response {
    /** @var ReasoningStep[] */
    public array $reasoning;
    public int $correct_answer;
}

class GenerateStructuredReasoning {
    public function __invoke(string $query, string $context) : Response {
        $system = <<<TXT
        <system>
            <role>expert Question Answering system</role>
            <instruction>Make sure to output your reasoning in structured reasoning steps before generating a response to the user's query.</instruction>
        </system>

        <context>
            {$context}
        </context>

        <query>
            {$query}
        </query>
        TXT;

        return (new StructuredOutput)->with(
            messages: [ ['role' => 'system', 'content' => $system] ],
            responseModel: Response::class,
        )->get();
    }
}

$query = 'How many loaves of bread did they have left?';
$context = <<<'CTX'
The bakers at the Beverly Hills Bakery baked
200 loaves of bread on Monday morning. They
sold 93 loaves in the morning and 39 loaves
in the afternoon. A grocery store returned 6
unsold loaves.
CTX;

$response = (new GenerateStructuredReasoning)($query, $context);
dump($response);
?>
```

### Sample Output

```json
{
  "reasoning": [
    {
      "step": 1,
      "subquestion": "How many loaves of bread were sold in the morning
        and afternoon?",
      "procedure": "93 (morning) + 39 (afternoon)",
      "result": "132"
    },
    { "step": 2, "subquestion": "How many loaves of bread were originally baked?", "procedure": "", "result": "200" },
    { "step": 3, "subquestion": "How many loaves of bread were returned by the grocery store?", "procedure": "", "result": "6" },
    { "step": 4, "subquestion": "How many loaves of bread were left after accounting for sales and returns?", "procedure": "200 - 132 + 6", "result": "74" }
  ],
  "correct_answer": 74
}
```

### References

1) Tab-CoT: Zero-shot Tabular Chain of Thought (https://arxiv.org/pdf/2305.17812)
