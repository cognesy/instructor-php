---
title: 'Customize parameters via DSN'
docname: 'custom_llm_with_dsn'
---

## Overview

You can provide your own LLM configuration data to `StructuredOutput` object with DSN string.
This is useful for inline configuration or for building configuration from admin UI,
CLI arguments or environment variables.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;

class User {
    public int $age;
    public string $name;
}

$user = (new StructuredOutput(StructuredOutputRuntime::fromDsn('preset=xai,model=grok-2')))
    //->wiretap(fn($e) => $e->print())
    ->withMessages("Our user Jason is 25 years old.")
    ->withresponseClass(User::class)
    ->get();

dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
