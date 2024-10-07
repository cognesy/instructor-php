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
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\LLM\Inference;
use Cognesy\Instructor\Extras\Structure\Field;
use Cognesy\Instructor\Extras\Structure\Structure;

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
        mode: Mode::JsonSchema,
    )
    ->toJson();

echo "USER: What is capital of France\n";
echo "ASSISTANT:\n";
dump($data);

?>
```