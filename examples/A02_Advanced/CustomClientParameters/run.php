---
title: 'Customize parameters of LLM driver'
docname: 'custom_config'
id: '5acf'
tags:
  - 'no-replay'
  - 'advanced'
  - 'custom-config'
  - 'llm-driver'
---
## Overview

You can provide your own LLM configuration instance to Instructor. This is useful
when you want to initialize OpenAI client with custom values - e.g. to call
other LLMs which support OpenAI API.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Config\Env;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Drivers\Symfony\SymfonyDriver;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Instructor\Enums\OutputMode;
use Symfony\Component\HttpClient\HttpClient as SymfonyHttpClient;

class User {
    public int $age;
    public string $name;
}

$events = new EventDispatcher();

// Build fully customized HTTP client

$httpConfig = new HttpClientConfig(
    connectTimeout: 30,
    requestTimeout: 60,
    idleTimeout: -1,
    failOnError: true,
);

$yourClientInstance = SymfonyHttpClient::create(['http_version' => '2.0']);

$clientProbe = new class implements HttpMiddleware {
    public int $requests = 0;

    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse
    {
        $this->requests++;

        return $next->handle($request);
    }
};

$customClient = (new HttpClientBuilder)
    ->withEventBus($events)
    ->withMiddleware($clientProbe)
    ->withDriver(new SymfonyDriver(
        config: $httpConfig,
        clientInstance: $yourClientInstance,
        events: $events,
    ))
    ->create();

// Create instance of LLM connection config initialized with custom parameters

$llmConfig = new LLMConfig(
    apiUrl  : 'https://api.deepseek.com',
    apiKey  : (string) Env::get('DEEPSEEK_API_KEY', ''),
    endpoint: '/chat/completions', model: 'deepseek-v4-flash', maxTokens: 512, driver: 'deepseek',
    options : ['temperature' => 0],
);

// Get Instructor with the default client component overridden with your own

$runtime = StructuredOutputRuntime::fromConfig(
    config: $llmConfig,
    events: $events,
    httpClient: $customClient,
)->withOutputMode(OutputMode::Json);
$runtime->wiretap(fn($e) => $e->print());

$structuredOutput = new StructuredOutput($runtime);

$user = $structuredOutput
    ->with("Our user Jason is 25 years old.")
    ->withResponseClass(User::class)
    ->withStreaming()
    ->get();

dump($user);

assert($user->name === 'Jason');
assert($user->age === 25);
assert($clientProbe->requests > 0, 'Expected the injected custom HTTP client to handle the request');
?>
```
