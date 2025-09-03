---
title: 'Working directly with LLMs and JSON - JSON Schema mode'
docname: 'llm_json_schema'
---

## Overview

While working with `Inference` class, you can also generate JSON output
from the model inference. This is useful for example when you need to
process the response in a structured way or when you want to store the
elements of the response in a database.

## Example

In this example we will use OpenAI JSON Schema mode, which guarantees
that the response will be in a JSON format that matches the provided
schema.

> NOTE: Json Schema mode with guaranteed structured outputs is not
supported by all language model providers.

```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;

$data = (new Inference)
    ->using('openai')
    ->with(
        messages: [['role' => 'user', 'content' => 'What is capital of France? \
        Respond with JSON data.']],
        responseFormat: [
            'type' => 'json_schema',
            'description' => 'City data',
            'json_schema' => [
                'name' => 'city_data',
                'schema' => [
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
                    'additionalProperties' => false,
                    'required' => ['name', 'founded', 'population'],
                ],
                'strict' => true,
            ],
        ],
        options: ['max_tokens' => 64],
        mode: OutputMode::JsonSchema,
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
