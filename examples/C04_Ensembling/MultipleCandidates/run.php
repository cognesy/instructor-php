---
title: 'Generate Multiple Candidate Responses'
docname: 'multiple_candidates'
---

## Overview

Generate multiple candidate responses and pick the most common answer (Self-Consistency).

## Example

```php
\<\?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class SelfConsistencyResponse {
    public string $chain_of_thought;
    public int $correct_answer;
}

class SelfConsistency {
    public function __invoke(string $prompt, int $k = 5) : int {
        $answers = [];
        for ($i = 0; $i < $k; $i++) { $answers[] = $this->one($prompt)->correct_answer; }
        $counts = [];
        foreach ($answers as $a) { $key = (string)$a; $counts[$key] = ($counts[$key] ?? 0) + 1; }
        arsort($counts);
        return (int) array_key_first($counts);
    }

    private function one(string $prompt) : SelfConsistencyResponse {
        $system = 'You are an intelligent QA system. First think step-by-step, then provide the final answer.';
        return (new StructuredOutput)->with(
            messages: [ ['role'=>'system','content'=>$system], ['role'=>'user','content'=>$prompt] ],
            responseModel: SelfConsistencyResponse::class,
            options: ['temperature' => 0.5],
        )->get();
    }
}

$prompt = <<<TXT
Janet's ducks lay 16 eggs per day.
She eats 3 for breakfast and bakes muffins with 4 each day.
She sells the remainder for $2 per egg. How much does she make per day?
TXT;

$answer = (new SelfConsistency)($prompt, k: 5);
dump($answer);
?>
```

### References

1) Self-Consistency Improves Chain Of Thought Reasoning In Language Models (https://arxiv.org/pdf/2210.03350)
