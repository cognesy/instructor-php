---
title: 'Use custom HTTP client instance - Laravel'
docname: 'custom_http_client_laravel'
id: '7d2d'
---
## Overview

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Illuminate\Http\Client\Factory;

class User {
    public int $age;
    public string $name;
}

$yourLaravelClientInstance = new Factory();

$user = (new StructuredOutput())
    ->using('openai')
    //->withDebugPreset('on')
    //->wiretap(fn($e) => $e->print())
    ->withLLMConfigOverrides(['apiUrl' => 'https://api.openai.com/v1'])
    ->withClientInstance(
        driverName: 'laravel',
        clientInstance: $yourLaravelClientInstance
    )
    ->withMessages("Our user Jason is 25 years old.")
    ->withResponseClass(User::class)
    ->withOutputMode(OutputMode::Tools)
    //->withStreaming()
    ->get();

dump($user);
assert(isset($user->name));
assert(isset($user->age));
?>
```
