---
title: 'Generating JSON Schema from PHP classes'
docname: 'json_schema'
id: '0f58'
---
## Overview

Instructor has a built-in support for dynamically constructing JSON Schema using
`JsonSchema` class. It is useful when you want to shape the structures during
runtime.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Utils\JsonSchema\JsonSchema;

$schema = JsonSchema::object(
    properties: [
        JsonSchema::string('name', 'User name'),
        JsonSchema::integer('age', 'User age'),
    ],
    requiredProperties: ['name', 'age'],
);

$user = (new StructuredOutput)
    ->withDebugPreset('on')
    ->withMessages("Jason is 25 years old and works as an engineer")
    ->withResponseJsonSchema($schema)
    ->withDeserializers()
    ->withDefaultToStdClass()
    ->get();

dump($user);

assert(gettype($user) === 'object');
assert(get_class($user) === 'stdClass');
assert(isset($user->name));
assert(isset($user->age));
assert($user->name === 'Jason');
assert($user->age === 25);

?>
```
