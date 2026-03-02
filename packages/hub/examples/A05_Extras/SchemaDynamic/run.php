---
title: 'Generating JSON Schema dynamically'
docname: 'schema_dynamic'
---

## Overview

Instructor can generate JSON Schema from runtime schemas.

Use `SchemaBuilder` to build the schema, then wrap it in `Structure`.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Dynamic\Structure;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Schema\SchemaBuilder;

$citySchema = SchemaBuilder::define('city')
    ->string('name', 'City name')
    ->int('population', 'City population')
    ->int('founded', 'Founding year')
    ->schema();

$city = Structure::fromSchema($citySchema);

$data = StructuredOutput::using('openai')
    //->withHttpDebugPreset('on')
    ->intoArray()
    ->withMessages([['role' => 'user', 'content' => 'What is capital of France? \
        Respond with JSON data.']])
    ->withResponseJsonSchema($city->toJsonSchema())
    ->withOptions(['max_tokens' => 64])
    ->withOutputMode(OutputMode::JsonSchema)
    ->get();

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
