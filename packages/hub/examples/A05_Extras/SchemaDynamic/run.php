---
title: 'Generating JSON Schema dynamically'
docname: 'schema_dynamic'
---

## Overview

Instructor has a built-in support for generating JSON Schema from
dynamic objects with `Structure` class.

This is useful when the data model is built during runtime or defined
by your app users.

`Structure` helps you flexibly design and modify data models that
can change with every request or user input and allows you to generate
JSON Schema for them.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\Extras\Structure\Field;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Polyglot\LLM\Inference;

$city = Structure::define('city', [
    Field::string('name', 'City name')->required(),
    Field::int('population', 'City population')->required(),
    Field::int('founded', 'Founding year')->required(),
]);

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
                'schema' => $city->toJsonSchema(),
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

?>
```
