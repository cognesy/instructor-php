---
title: 'Working directly with LLMs and JSON - Tools mode'
docname: 'llm_tools'
---

## Overview

While working with `Inference` class, you can also generate JSON output
from the model inference. This is useful for example when you need to
process the response in a structured way or when you want to store the
elements of the response in a database.

## Example

In this example we will use OpenAI tools mode, in which model will generate
a JSON containing arguments for a function call. This way we can make the
model generate a JSON object with specific structure of parameters.

```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Polyglot\LLM\Inference;

$data = (new Inference)
    ->withConnection('openai')
    ->create(
        messages: [['role' => 'user', 'content' => 'What is capital of France? \
           Respond with function call.']],
        tools: [[
            'type' => 'function',
            'function' => [
                'name' => 'extract_data',
                'description' => 'Extract city data',
                'parameters' => [
                    'type' => 'object',
                    'description' => 'City information',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'City name',
                        ],
                        'founded' => [
                            'type' => 'integer',
                            'description' => 'Founding year',
                        ],
                        'population' => [
                            'type' => 'integer',
                            'description' => 'Current population',
                        ],
                    ],
                    'required' => ['name', 'founded', 'population'],
                    'additionalProperties' => false,
                ],
            ],
        ]],
        toolChoice: [
            'type' => 'function',
            'function' => [
                'name' => 'extract_data'
            ]
        ],
        options: ['max_tokens' => 64],
        mode: OutputMode::Tools,
    )
    ->toJson();

echo "USER: What is capital of France\n";
echo "ASSISTANT:\n";
dump($data);

?>
```
