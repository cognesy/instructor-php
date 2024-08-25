---
title: 'Modes'
docname: 'modes'
---

## Overview

Instructor supports several ways to extract data from the response:

 - `Mode::Tools` - uses OpenAI-style tool calls to get the language
   model to generate JSON following the schema,
 - `Mode::JsonSchema` - guarantees output matching JSON Schema via
   Context Free Grammar, does not support optional properties,
 - `Mode::Json` - JSON mode, response follows provided JSON Schema,
 - `Mode::MdJson` - uses prompting to get the language model to
   generate JSON following the schema.

Note: not all modes are supported by all models or providers.

Mode can be set via parameter of `Instructor::response()` or `Instructor::request()`
methods.

## Example

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Instructor;

class User {
    public int $age;
    public string $name;
}

$text = "Jason is 25 years old and works as an engineer.";

print("Input text:\n");
print($text . "\n\n");

$instructor = (new Instructor)->withDebug();

// CASE 1 - Mode::Tools
print("\n1. Extracting structured data using LLM - Mode::Tools\n");
$user = $instructor->respond(
    messages: $text,
    responseModel: User::class,
    mode: Mode::Tools,
);
check($user);
dump($user);

// CASE 2 - Mode::JsonSchema
print("\n2. Extracting structured data using LLM - Mode::JsonSchema\n");
$user = $instructor->respond(
    messages: $text,
    responseModel: User::class,
    mode: Mode::JsonSchema,
);
check($user);
dump($user);

// CASE 3 - Mode::Json
print("\n3. Extracting structured data using LLM - Mode::Json\n");
$user = $instructor->respond(
    messages: $text,
    responseModel: User::class,
    mode: Mode::Json,
);
check($user);
dump($user);

// CASE 4 - Mode::MdJson
print("\n4. Extracting structured data using LLM - Mode::MdJson\n");
$user = $instructor->respond(
    messages: $text,
    responseModel: User::class,
    mode: Mode::MdJson,
);
check($user);
dump($user);

function check(User $user) {
    assert(isset($user->name));
    assert(isset($user->age));
    assert($user->name === 'Jason');
    assert($user->age === 25);
}
?>
```
