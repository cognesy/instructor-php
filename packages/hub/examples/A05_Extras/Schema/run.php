---
title: 'Generating JSON Schema from PHP classes'
docname: 'schema'
---

## Overview

Instructor has a built-in support for generating JSON Schema from
the classes or objects. This is useful as it helps you avoid writing
the JSON Schema manually, which can be error-prone and time-consuming.

## Example

```php
\<\?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Schema\Factories\SchemaFactory;

class City {
    public string $name;
    public int $population;
    public int $founded;
}

$schema = (new SchemaFactory)->schema(City::class);

$city = (new StructuredOutput)->with(
    messages: "What is capital of France",
    responseModel: $schema,
)->get();

dump($city);

assert(gettype($city) === 'object');
assert(get_class($city) === 'City');
assert($city->name === 'Paris');
assert(is_int($city->population));
assert(is_int($city->founded));

?>
```
