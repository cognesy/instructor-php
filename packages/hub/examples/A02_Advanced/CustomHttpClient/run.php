---
title: 'Use custom HTTP client instance'
docname: 'custom_http_client'
---

## Overview

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Events\Dispatchers\SymfonyEventDispatcher;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\LLMProvider;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpClient\HttpClient;

// custom Symfony components
// custom Symfony components


class User {
    public int $age;
    public string $name;
}

// Call with custom model and execution mode

$yourSymfonyClientInstance = HttpClient::create(['http_version' => '2.0']);
$yourSymfonyEventDispatcher = new SymfonyEventDispatcher(new EventDispatcher());
$provider = LLMProvider::using('openai')
    ->withConfigOverrides(['apiUrl' => 'https://api.openai.com/v1']);
$customClient = (new HttpClientBuilder(events: $yourSymfonyEventDispatcher))
    ->withClientInstance(
        driverName: 'symfony',
        clientInstance: $yourSymfonyClientInstance,
    )
    ->create();

$user = (new StructuredOutput(
    runtime: StructuredOutputRuntime::fromProvider(
        provider: $provider,
        events: $yourSymfonyEventDispatcher,
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
