---
title: 'Solve Simpler Subproblems'
docname: 'solve_simpler_subtasks'
---

## Overview

How can we encourage an LLM to solve complex problems by breaking them down?

Least-to-Most is a prompting technique that breaks a complex problem down into a series of increasingly complex subproblems.

**Subproblems Example:**
- Original problem: Adam is twice as old as Mary. Adam will be 11 in 1 year. How old is Mary?
- Subproblems: (1) How old is Adam now? (2) What is half of Adam's current age?

These subproblems are solved sequentially, allowing the answers from earlier (simpler) subproblems to inform the LLM while solving later (more complex) subproblems.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;

class Subquestion {
    public function __construct(
        public string $question
    ) {}
}

class Answer {
    public function __construct(
        public int $answer
    ) {}
}

class SubquestionWithAnswer {
    public function __construct(
        public string $question,
        public int $answer
    ) {}
}

class LeastToMostSolver {
    public function __invoke(string $question): array {
        $subquestions = $this->decompose($question);
        return $this->solveSequentially($subquestions, $question);
    }
    
    private function decompose(string $question): array {
        return (new StructuredOutput)->with(
            messages: [
                [
                    'role' => 'user', 
                    'content' => "Break this question down into subquestions to solve sequentially: {$question}"
                ],
            ],
            responseModel: Sequence::of(Subquestion::class),
        )->get()->toArray();
    }
    
    private function solve(string $question, array $solvedQuestions, string $originalQuestion): int {
        $solvedContext = '';
        foreach ($solvedQuestions as $solved) {
            $solvedContext .= "{$solved->question} {$solved->answer}\n";
        }
        
        return (new StructuredOutput)->with(
            messages: [
                [
                    'role' => 'user',
                    'content' => <<<PROMPT
                        <original_question>
                        {$originalQuestion}
                        </original_question>
                        
                        <solved_subquestions>
                        {$solvedContext}
                        </solved_subquestions>
                        
                        Solve this next subquestion: {$question}
                    PROMPT,
                ],
            ],
            responseModel: Answer::class,
        )->get()->answer;
    }
    
    private function solveSequentially(array $subquestions, string $originalQuestion): array {
        $solvedQuestions = [];
        
        foreach ($subquestions as $subquestion) {
            $answer = $this->solve($subquestion->question, $solvedQuestions, $originalQuestion);
            $solvedQuestions[] = new SubquestionWithAnswer($subquestion->question, $answer);
        }
        
        return $solvedQuestions;
    }
}

$results = (new LeastToMostSolver)(
    "Four years ago, Kody was only half as old as Mohamed. If Mohamed is currently twice 30 years old, how old is Kody?"
);

foreach ($results as $result) {
    echo "{$result->question} {$result->answer}\n";
}

dump($results);
?>
```

## References

1. [Least-to-Most Prompting Enables Complex Reasoning in Large Language Models](https://arxiv.org/abs/2205.10625)
2. [The Prompt Report: A Systematic Survey of Prompting Techniques](https://arxiv.org/abs/2406.06608)