---
title: 'Ask Model to Repeat the Query'
docname: 'repeat_query'
---

## Overview

How can we enhance a model's understanding of a query?

Re2 (Re-Reading) is a technique that asks the model to read the question again.

<Tip>
### Re-Reading Prompting

Prompt Template:
 - Read the question again: [query]
 - [critical thinking prompt]

A common critical thinking prompt is: "Let's think step by step."
</Tip>

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\Features\Schema\Attributes\Description;
use Cognesy\Instructor\StructuredOutput;

class Response {
    #[Description("Repeat user's query.")]
    public string $query;
    #[Description("Let's think step by step.")]
    public string $thoughts;
    public int $answer;
}

class RereadAndRespond {
    public function __invoke(string $query) : Response {
        return (new StructuredOutput)->with(
            messages: $query,
            responseModel: Response::class,
        )->get();
    }
}

$response = (new RereadAndRespond)(
    query: <<<QUERY
        Roger has 5 tennis balls. He buys 2 more cans of tennis balls.
        Each can has 3 tennis balls.
        How many tennis balls does he have now?
    QUERY,
);

echo "Answer:\n";
dump($response);
?>
```

## References

 1. [Re-Reading Improves Reasoning in Large Language Models](https://arxiv.org/abs/2309.06275)
