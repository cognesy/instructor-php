---
title: 'Generating JSON Schema from PHP classes'
docname: 'llm_with_schema_helper'
id: '4391'
tags:
  - 'llm'
  - 'json-schema'
  - 'schema-helper'
---
## Overview

Polyglot has a built-in support for dynamically constructing JSON Schema using
`JsonSchema` class. It is useful when you want to shape the structures during
runtime.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
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

$data = Inference::using('openai')
    ->with(
        messages: Messages::fromString('What is capital of France? Respond with JSON data.'),
        responseFormat: ResponseFormat::fromArray($schema->toResponseFormat(
            schemaName: 'city_data',
            schemaDescription: 'City data',
            strict: true,
        )),
        options: ['max_tokens' => 64],
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
