---
title: 'Generate Prompt Variations'
docname: 'prompt_variations'
---

## Overview

Large Language Models are sensitive to prompt phrasing. Prompt Mining helps discover better templates that occur more frequently in the corpus or are clearer to the model.

Here are examples from the paper mapping manual prompts to mined prompts:

| Manual Prompt | Mined Prompt |
| --- | --- |
| x is affiliated with the y religion | x who converted to y |
| The headquarter of x is in y | x is based in y |
| x died in y | x died at his home in y |
| x is represented by music label y | x recorded for y |
| x is a subclass of y | x is a type of y |

We implement a lightweight approach with Instructor to extract clearer prompt templates.

## Example

```php
\<\?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Extras\Sequence\Sequence;

class PromptTemplate {
    public string $prompt_template;
}

class GeneratePromptTemplates {
    public function __invoke(string $prompt) : array {
        $system = 'You are an expert prompt miner that generates 3 clearer, concise prompt templates.';
        return (new StructuredOutput)->with(
            messages: [
                ['role' => 'system', 'content' => $system],
                ['role' => 'system', 'content' => $prompt],
            ],
            responseModel: Sequence::of(PromptTemplate::class),
        )->get()->toArray();
    }
}

$prompt = 'France is the capital of Paris';
$templates = (new GeneratePromptTemplates)($prompt);
dump($templates);
?>
```

### References

1) How Can We Know What Language Models Know? (https://direct.mit.edu/tacl/article/doi/10.1162/tacl_a_00324/96460/How-Can-We-Know-What-Language-Models-Know)
