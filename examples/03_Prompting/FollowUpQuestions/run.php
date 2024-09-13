---
title: 'Generate Follow-Up Questions'
docname: 'follow_up_questions'
---

## Overview

Models can sometimes correctly answer sub-problems but incorrectly answer the overall query. This is known as the compositionality gap1.

How can we encourage a model to use the answers to sub-problems to correctly generate the overall solution?

Self-Ask is a technique which use a single prompt to:
 - decide if follow-up questions are required
 - generate the follow-up questions
 - answer the follow-up questions
 - answer the main query

## Example

```php
<?php

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Schema\Attributes\Description;

class FollowUp {
    #[Description("The follow-up question")]
    public string $question;
    #[Description("The answer to the follow-up question")]
    public string $answer;
}

class Response {
    public bool $followUpsRequired;
    /** @var FollowUp[] */
    public array $followUps;
    public string $finalAnswer;
}

class RespondWithFollowUp {
    private $prompt = <<<QUERY
        Query: {query}
        Are follow-up questions needed?
        If so, generate follow-up questions, their answers, and then the final answer to the query.
    QUERY;

    public function __invoke(string $query) : Response {
        return (new Instructor)->respond(
            messages: str_replace('{query}', $query, $this->prompt),
            responseModel: Response::class,
        );
    }
}

$response = (new RespondWithFollowUp)(
    query: "Who succeeded the president of France ruling when Bulgaria joined EU?",
);

echo "Answer:\n";
dump($response);
?>
```

## References

 1. [Measuring and Narrowing the Compositionality Gap in Language Models](https://arxiv.org/abs/2210.03350)
