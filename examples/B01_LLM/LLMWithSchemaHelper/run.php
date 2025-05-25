---
title: 'Generating JSON Schema from PHP classes'
docname: 'json_schema_api'
---

## Overview

Polyglot has a built-in support for dynamically constructing JSON Schema using
`JsonSchema` class. It is useful when you want to shape the structures during
runtime.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Utils\JsonSchema\JsonSchema;

$schema = JsonSchema::object(
    properties: [
        JsonSchema::string('name', 'City name'),
        JsonSchema::integer('population', 'City population'),
        JsonSchema::integer('founded', 'Founding year'),
    ],
    requiredProperties: ['name', 'population', 'founded'],
);

$data = (new Inference)
    ->using('openai')
    ->with(
        messages: [
            ['role' => 'user', 'content' => 'What is capital of France? Respond with JSON data.']
        ],
        responseFormat: [
            'type' => 'json_schema',
            'description' => 'City data',
            'json_schema' => [
                'name' => 'city_data',
                'schema' => $schema->toJsonSchema(),
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

assert(is_array($data));
assert(is_string($data['name']));
assert(is_int($data['population']));
assert(is_int($data['founded']));
?>
```
