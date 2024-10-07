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
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Enums\Mode;use Cognesy\Instructor\Extras\LLM\Inference;

$data = (new Inference)
    ->withConnection('openai')
    ->create(
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
        mode: Mode::JsonSchema,
    )
    ->toJson();

echo "USER: What is capital of France\n";
echo "ASSISTANT:\n";
dump($data);

?>
```