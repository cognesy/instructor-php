---
title: 'Generating JSON Schema from PHP classes'
docname: 'json_schema_api'
---

## Overview

Polyglot has a built-in support for dynamically constructing tool calling schema using
`JsonSchema` class.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;
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
    //->withHttpDebugPreset('on')
    ->with(
        messages: [
            ['role' => 'user', 'content' => 'What is capital of France? Respond with function call.']
        ],
        tools: [
            $schema->toFunctionCall(
               functionName: 'provide_data',
               functionDescription: 'Provide city data'
            )
        ],
        toolChoice: [
            'type' => 'function',
            'function' => [
                'name' => 'provide_data'
            ]
        ],
        options: ['max_tokens' => 64],
        mode: OutputMode::Tools,
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
