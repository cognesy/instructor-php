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

use Adbar\Dot;
use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Config\Env;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Events\Event;
use Cognesy\Http\HttpClientBuilder;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;
use Symfony\Component\HttpClient\HttpClient as SymfonyHttpClient;

$configData = [
    'http' => [
        'defaultPreset' => 'symfony',
        'presets' => [
            'symfony' => [
                'driver' => 'symfony',
                'connectTimeout' => 10,
                'requestTimeout' => 30,
                'idleTimeout' => -1,
                'maxConcurrent' => 5,
                'poolTimeout' => 60,
                'failOnError' => true,
            ],
            // Add more HTTP presets as needed
        ],
    ],
    'debug' => [
        'defaultPreset' => 'off',
        'presets' => [
            'off' => [
                'httpEnabled' => false,
            ],
            'on' => [
                'httpEnabled' => true,
                'httpTrace' => true,
                'httpRequestUrl' => true,
                'httpRequestHeaders' => true,
                'httpRequestBody' => true,
                'httpResponseHeaders' => true,
                'httpResponseBody' => true,
                'httpResponseStream' => true,
                'httpResponseStreamByLine' => true,
            ],
        ],
    ],
    'llm' => [
        'defaultPreset' => 'deepseek',
        'presets' => [
            'deepseek' => [
                'apiUrl' => 'https://api.deepseek.com',
                'apiKey' => Env::get('DEEPSEEK_API_KEY'),
                'endpoint' => '/chat/completions',
                'model' => 'deepseek-chat',
                'maxTokens' => 128,
                'driver' => 'deepseek',
                'httpClientPreset' => 'symfony',
            ],
            'openai' => [
                'apiUrl' => 'https://api.openai.com',
                'apiKey' => Env::get('OPENAI_API_KEY'),
                'endpoint' => '/v1/chat/completions',
                'model' => 'gpt-4',
                'maxTokens' => 256,
                'driver' => 'openai',
                'httpClientPreset' => 'symfony',
            ],
        ],
    ],
];

class CustomConfigProvider implements CanProvideConfig
{
    private Dot $dot;

    public function __construct(array $data = []) {
        $this->dot = new Dot($data);
    }

    public function get(string $path, mixed $default = null): mixed {
        return $this->dot->get($path, $default);
    }

    public function has(string $path): bool {
        return $this->dot->has($path);
    }
}

$configProvider = new CustomConfigProvider($configData);

$events = new EventDispatcher();
$customClient = (new HttpClientBuilder(
        events: $events,
        configProvider: $configProvider,
    ))
    ->withClientInstance(
        driverName: 'symfony',
        clientInstance: SymfonyHttpClient::create(['http_version' => '2.0']),
    )
    ->create();

$inference = (new Inference(
        events: $events,
        configProvider: $configProvider,
    ))
    ->withHttpClient($customClient);

$answer = $inference
    ->using('deepseek') // Use 'deepseek' preset from CustomLLMConfigProvider
    //->withDebugPreset('on')
    ->wiretap(fn(Event $e) => $e->print())
    ->withMessages([['role' => 'user', 'content' => 'What is the capital of France']])
    ->withMaxTokens(256)
    ->withStreaming()
    ->get();

echo "USER: What is capital of France\n";
echo "ASSISTANT: $answer\n";

assert(Str::contains($answer, 'Paris'));
?>
```
