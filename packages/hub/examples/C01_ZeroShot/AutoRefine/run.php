---
title: 'Auto-Refine The Prompt'
docname: 'auto_refine'
---

## Overview

How do we remove irrelevant information from the prompt?

The S2A (System 2 Attention) technique auto-refines a prompt by asking the model to
rewrite the prompt to include only relevant information.

We implement this in two steps:

 1. Ask the model to rewrite the prompt
 2. Pass the rewritten prompt back to the model

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\Extras\Scalar\Scalar;
use Cognesy\Instructor\Features\Schema\Attributes\Description;
use Cognesy\Instructor\Instructor;

class RewrittenTask {
    #[Description("Relevant context")]
    public string $relevantContext;
    #[Description("The question from the user")]
    public string $userQuery;
}

class RefineAndSolve {
    private string $prompt = <<<PROMPT
        Given the following text by a user, extract the part
        that is actually relevant to their question. Please
        include the actual question or query that the user
        is asking.
        
        Text by user:
        {query}
        PROMPT;

    public function __invoke(string $problem) : int {
        $rewrittenPrompt = $this->rewritePrompt($problem);
        return (new Instructor)->respond(
            messages: "{$rewrittenPrompt->relevantContext}\nQuestion: {$rewrittenPrompt->userQuery}",
            responseModel: Scalar::integer('answer'),
        );
    }

    private function rewritePrompt(string $query) : RewrittenTask {
        return (new Instructor)->respond(
            messages: str_replace('{query}', $query, $this->prompt),
            responseModel: RewrittenTask::class,
            model: 'gpt-4o',
        );
    }
}

$answer = (new RefineAndSolve)(problem: <<<PROBLEM
    Mary has 3 times as much candy as Megan.
    Mary then adds 10 more pieces of candy to her collection.
    Max is 5 years older than Mary.
    If Megan has 5 pieces of candy, how many does Mary have in total?
    PROBLEM,
);

echo $answer . "\n";
?>
```

## References

 1. [System 2 Attention (is something you might need too)](https://arxiv.org/abs/2311.11829)



