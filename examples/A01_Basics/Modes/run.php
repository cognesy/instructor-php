---
title: 'Modes'
docname: 'modes'
---

## Overview

Instructor supports several ways to extract data from the response:

 - `OutputMode::Tools` - uses OpenAI-style tool calls to get the language
   model to generate JSON following the schema,
 - `OutputMode::JsonSchema` - guarantees output matching JSON Schema via
   Context Free Grammar, does not support optional properties,
 - `OutputMode::Json` - JSON mode, response follows provided JSON Schema,
 - `OutputMode::MdJson` - uses prompting to get the language model to
   generate JSON following the schema.

Note: not all modes are supported by all models or providers.

Mode can be set via parameter of `StructuredOutput::create()` method.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

class User {
    public int $age;
    public string $name;
}

$text = "Jason is 25 years old and works as an engineer.";

print("Input text:\n");
print($text . "\n\n");

$structuredOutput = new StructuredOutput;

// CASE 1 - OutputMode::Tools
print("\n1. Extracting structured data using LLM - OutputMode::Tools\n");
$user = $structuredOutput->with(
    messages: $text,
    responseModel: User::class,
    mode: OutputMode::Tools,
)->get();
check($user);
dump($user);

// CASE 2 - OutputMode::JsonSchema
print("\n2. Extracting structured data using LLM - OutputMode::JsonSchema\n");
$user = $structuredOutput->with(
    messages: $text,
    responseModel: User::class,
    mode: OutputMode::JsonSchema,
)->get();
check($user);
dump($user);

// CASE 3 - OutputMode::Json
print("\n3. Extracting structured data using LLM - OutputMode::Json\n");
$user = $structuredOutput->with(
    messages: $text,
    responseModel: User::class,
    mode: OutputMode::Json,
)->get();
check($user);
dump($user);

// CASE 4 - OutputMode::MdJson
print("\n4. Extracting structured data using LLM - OutputMode::MdJson\n");
$user = $structuredOutput->with(
    messages: $text,
    responseModel: User::class,
    mode: OutputMode::MdJson,
)->get();
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
