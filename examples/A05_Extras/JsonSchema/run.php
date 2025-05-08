---
title: 'Generating JSON Schema from PHP classes'
docname: 'schema'
---

## Overview

Instructor has a built-in support for dynamically constructing JSON Schema using
`JsonSchema` class. It is useful when you want to shape the structures during
runtime.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Instructor\Instructor;

$schema = JsonSchema::object(
    properties: [
        JsonSchema::string('name', 'User name'),
        JsonSchema::integer('age', 'User age'),
    ],
    requiredProperties: ['name', 'age'],
);

$user = (new Instructor)->respond(
    messages: "Jason is 25 years old and works as an engineer",
    responseModel: $schema,
);

dump($user);

assert(gettype($user) === 'object');
assert(get_class($user) === 'stdClass');
assert(isset($user->name));
assert(isset($user->age));
assert($user->name === 'Jason');
assert($user->age === 25);

?>
```
