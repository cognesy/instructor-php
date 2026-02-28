---
title: 'Use custom HTTP client instance - Laravel'
docname: 'custom_http_client_laravel'
---

## Overview

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Http\Creation\HttpClientBuilder;
use Illuminate\Http\Client\Factory;

class User {
    public int $age;
    public string $name;
}

$yourLaravelClientInstance = new Factory();
$provider = LLMProvider::using('openai')
    ->withConfigOverrides(['apiUrl' => 'https://api.openai.com/v1']);
$customClient = (new HttpClientBuilder)
    ->withClientInstance(
        driverName: 'laravel',
        clientInstance: $yourLaravelClientInstance,
    )
    ->create();

$user = (new StructuredOutput(
    runtime: StructuredOutputRuntime::fromProvider(
        provider: $provider,
        httpClient: $customClient,
    ),
))
    //->wiretap(fn($e) => $e->print())
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
