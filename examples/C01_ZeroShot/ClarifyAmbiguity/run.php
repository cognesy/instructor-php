---
title: 'Clarify Ambiguous Information'
docname: 'clarify_ambiguity'
---

## Overview

How can we identify and clarify ambiguous information in the prompt?

Let's say we are given the query: Was Ed Sheeran born on an odd month?

There are many ways a model might interpret an odd month:
 - February is odd because of an irregular number of days.
 - A month is odd if it has an odd number of days.
 - A month is odd if its numerical order in the year is odd (i.e. January is the 1st month).

<Warning>Ambiguities might not always be so obvious!</Warning>

To help the model better infer human intention from ambiguous prompts,
we can ask the model to rephrase and respond (RaR) in a single step -
which is demonstrated in this example.

This can also be implemented as two-step RaR:
 - Ask the model to rephrase the question to clarify any ambiguities.
 - Pass the rephrased question back to the model to generate the final response.

## Example

```php
\<\?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class Response {
    public string $rephrasedQuestion;
    public string $answer;
}

class Disambiguate {
    private $prompt = <<<PROMPT
        Rephrase and expand the question to address any potential ambiguities, then respond.
        Question: {query}
        PROMPT;

    public function __invoke(string $query) : Response {
        return (new StructuredOutput)->with(
            messages: str_replace('{query}', $query, $this->prompt),
            responseModel: Response::class,
        )->get();
    }
}

$response = (new Disambiguate)(query: "What is an object");

dump($response);
?>
```

## References

 1. [Rephrase and Respond: Let Large Language Models Ask Better Questions for Themselves](https://arxiv.org/abs/2311.04205)
