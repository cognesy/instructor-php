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

use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;

$data = (new Inference)
    ->using('openai')
    ->with(
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
    ->asJsonData();

echo "USER: What is capital of France\n";
echo "ASSISTANT:\n";
dump($data);

assert(is_array($data), 'Response should be an array');
assert(isset($data['name']), 'Response should have "name" field');
assert(strpos($data['name'], 'Paris') !== false, 'City name should be Paris');
assert(isset($data['population']), 'Response should have "population" field');
assert(isset($data['founded']), 'Response should have "founded" field');
?>
```
