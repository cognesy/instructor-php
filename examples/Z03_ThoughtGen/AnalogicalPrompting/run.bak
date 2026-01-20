---
title: 'Analogical Prompting'
docname: 'analogical_prompting'
---

## Overview

### Generate Examples First

Analogical Prompting is a method that aims to get LLMs to generate
examples that are relevant to the problem before starting to address
the user's query.

This takes advantage of the various forms of knowledge that the LLM
has acquired during training and explicitly prompts them to recall
the relevant problems and solutions. We can use Analogical Prompting
using the following template

<Tip>
Analogical Prompting Prompt Template

 - Problem: `[user prompt]`
 - Relevant Problems: Recall `[n]` relevant and distinct problems.
 - For each problem, describe it and explain the solution
</Tip>


## Example

We can implement this using Instructor to solve the problem, as seen below
with some slight modifications.


```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class Problem {
    public string $problemExplanation;
    public string $solution;
}

class Response {
    /** @var Problem[] */
    public array $relevantProblems;
    public Problem $problemSolution;
    public string $answer;
}

class SolvePerAnalogy {
    private int $n = 3;
    private string $prompt = <<<PROMPT
        <problem>
        {query}
        </problem>
        
        Relevant Problems: Recall {n} relevant and
        distinct problems. For each problem, describe
        it and explain the solution before solving
        the problem    
    PROMPT;

    public function __invoke(string $query) : Response {
        return (new StructuredOutput)->with(
            messages: str_replace(['{n}', '{query}'], [$this->n, $query], $this->prompt),
            responseModel: Response::class,
        )->get();
    }
}

$solution = (new SolvePerAnalogy)('What is the area of the square with the four vertices at (-2, 2), (2, -2), (-2, -6), and (-6, -2)?');

dump($solution);
?>
```

## References

 1. [Large Language Models As Analogical Reasoners](https://arxiv.org/pdf/2310.01714)
