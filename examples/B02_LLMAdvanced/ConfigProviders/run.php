---
title: 'Customize configuration providers of LLM driver'
docname: 'config_providers'
---

## Overview

You can provide your own LLM configuration instance to `Inference` object. This is useful
when you want to initialize LLM client with custom values.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Http\Contracts\CanProvideDebugConfig;
use Cognesy\Http\Contracts\CanProvideHttpClientConfig;
use Cognesy\Http\Data\HttpClientConfig;
use Cognesy\Http\Debug\DebugConfig;
use Cognesy\Http\HttpClientBuilder;
use Cognesy\Polyglot\LLM\Contracts\CanProvideLLMConfig;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Utils\Config\Env;
use Cognesy\Utils\Events\Event;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Str;
use Symfony\Component\HttpClient\HttpClient as SymfonyHttpClient;

$events = new EventDispatcher();

class CustomHttpConfigProvider implements CanProvideHttpClientConfig
{
    public function getConfig(?string $preset = ''): HttpClientConfig {
        return new HttpClientConfig(
            driver: 'symfony',
            connectTimeout: 10,
            requestTimeout: 30,
            idleTimeout: -1,
            maxConcurrent: 5,
            poolTimeout: 60,
            failOnError: true,
        );
    }
}

class CustomDebugConfigProvider implements CanProvideDebugConfig
{
    public function getConfig(?string $preset = ''): DebugConfig {
        return $this->presets()[$preset] ?? new DebugConfig();
    }

    private function presets(): array {
        return [
            'off' => new DebugConfig(
                httpEnabled: true,
            ),
            'on' => new DebugConfig(
                httpEnabled: true,
                httpTrace: true,
                httpRequestUrl: true,
                httpRequestHeaders: true,
                httpRequestBody: true,
                httpResponseHeaders: true,
                httpResponseBody: true,
                httpResponseStream: true,
                httpResponseStreamByLine: true,
            )
        ];
    }
}

$customClient = (new HttpClientBuilder(
        events: $events,
        listener: $events,
        httpConfigProvider: new CustomHttpConfigProvider(),
        debugConfigProvider: new CustomDebugConfigProvider(),
    ))
    ->withClientInstance(SymfonyHttpClient::create(['http_version' => '2.0']))
    ->withDebugPreset('on')
    ->create();

class CustomLLMConfigProvider implements CanProvideLLMConfig {
    public function getConfig(?string $preset = null): LLMConfig {
        return $this->presets()[$preset] ?? new LLMConfig();
    }
    private function presets(): array {
        return [
            'deepseek' => new LLMConfig(
                apiUrl  : 'https://api.deepseek.com',
                apiKey  : Env::get('DEEPSEEK_API_KEY'),
                endpoint: '/chat/completions', defaultModel: 'deepseek-chat', defaultMaxTokens: 128,
                driver  : 'deepseek',
            ),
            // ...
        ];
    }
};

$inference = (new Inference(
        events: $events,
        listener: $events,
        configProvider: new CustomLLMConfigProvider()
    ))
    ->withHttpClient($customClient);

$answer = $inference->using('deepseek') // Use 'deepseek' preset from CustomLLMConfigProvider
    ->wiretap(fn(Event $e) => $e->print())
    ->with(
        messages: [['role' => 'user', 'content' => 'What is the capital of France']],
        options: ['max_tokens' => 64]
    )
    ->withStreaming()
    ->get();

echo "USER: What is capital of France\n";
echo "ASSISTANT: $answer\n";

assert(Str::contains($answer, 'Paris'));
?>
```
