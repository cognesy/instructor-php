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
use Cognesy\Events\Event;
use Cognesy\Events\EventDispatcher;
use Cognesy\Http\HttpClientBuilder;
use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Utils\Str;
use Symfony\Component\HttpClient\HttpClient as SymfonyHttpClient;

class CustomConfigProvider implements CanProvideConfig
{
    private Dot $dot;

    public function __construct(array $config = []) {
        $this->dot = new Dot($config);
    }

    public function get(string $path, mixed $default = null): mixed {
        return $this->dot->get($path, $default);
    }

    public function has(string $path): bool {
        return $this->dot->has($path);
    }
}

$configData = [
    'http' => [
        'default' => 'symfony',
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
        'default' => 'off',
        'presets' => [
            'off' => [
                'httpEnabled' => true,
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
        'default' => 'deepseek',
        'presets' => [
            'deepseek' => [
                'apiUrl' => 'https://api.deepseek.com',
                'apiKey' => Env::get('DEEPSEEK_API_KEY'),
                'endpoint' => '/chat/completions',
                'defaultModel' => 'deepseek-chat',
                'defaultMaxTokens' => 128,
                'driver' => 'deepseek',
                'httpClientPreset' => 'symfony',
            ],
            'openai' => [
                'apiUrl' => 'https://api.openai.com',
                'apiKey' => Env::get('OPENAI_API_KEY'),
                'endpoint' => '/v1/chat/completions',
                'defaultModel' => 'gpt-4',
                'defaultMaxTokens' => 256,
                'driver' => 'openai',
                'httpClientPreset' => 'symfony',
            ],
        ],
    ],
];

// Create ArrayConfigProvider
$configProvider = new CustomConfigProvider($configData);
$events = new EventDispatcher();

$customClient = (new HttpClientBuilder(
        events: $events,
        listener: $events,
        configProvider: new CustomConfigProvider(),
    ))
    ->withClientInstance(SymfonyHttpClient::create(['http_version' => '2.0']))
    ->withDebugPreset('on')
    ->create();

$inference = (new Inference(
        events: $events,
        listener: $events,
        configProvider: new CustomConfigProvider()
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
