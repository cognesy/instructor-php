---
title: 'Customize parameters via DSN'
docname: 'custom_llm_with_dsn'
---

## Overview

You can provide your own LLM configuration data to `Instructor` object with DSN string.
This is useful for inline configuration or for building configuration from admin UI,
CLI arguments or environment variables.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Env;

class User {
    public int $age;
    public string $name;
}

$user = (new StructuredOutput)
    ::fromDSN('connection=xai,model=grok-2')
    ->create(
        messages: "Our user Jason is 25 years old.",
        responseModel: User::class,
        mode: OutputMode::Tools,
    )->get();

dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
