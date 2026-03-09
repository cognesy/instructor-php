---
title: 'Customize parameters via DSN'
docname: 'custom_llm_with_dsn'
id: 'fc0f'
---
## Overview

You can provide your own LLM configuration data to `StructuredOutput` object with DSN string.
This is useful for inline configuration or for building configuration from admin UI,
CLI arguments or environment variables.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Config\Env;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

class User {
    public int $age;
    public string $name;
}

$xaiApiKey = (string) Env::get('XAI_API_KEY', '');
$dsn = "driver=xai,apiUrl=https://api.x.ai/v1,endpoint=/chat/completions,apiKey={$xaiApiKey},model=grok-3";

$user = (new StructuredOutput(StructuredOutputRuntime::fromConfig(LLMConfig::fromDsn($dsn))))
    //->wiretap(fn($e) => $e->print())
    ->withMessages("Our user Jason is 25 years old.")
    ->withResponseClass(User::class)
    ->get();

dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
