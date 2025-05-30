---
title: 'Customize parameters of LLM driver'
docname: 'custom_llm'
---

## Overview

You can provide your own LLM configuration instance to `Inference` object. This is useful
when you want to initialize LLM client with custom values.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Http\Data\HttpClientConfig;
use Cognesy\Http\Drivers\SymfonyDriver;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Utils\Env;
use Cognesy\Utils\Str;
use Symfony\Component\HttpClient\HttpClient as SymfonyHttpClient;

// Create instance of LLM client initialized with custom parameters
$config = new LLMConfig(
    apiUrl: 'https://api.deepseek.com',
    apiKey: Env::get('DEEPSEEK_API_KEY'),
    endpoint: '/chat/completions',
    model: 'deepseek-chat',
    maxTokens: 128,
    httpClient: 'guzzle',
    providerType: 'deepseek',
);

// Build fully customized HTTP client
$httpConfig = new HttpClientConfig(
    httpClientType: 'symfony',
    connectTimeout: 5,
    requestTimeout: 60,
    idleTimeout: -1,
    maxConcurrent: 5,
    poolTimeout: 60,
    failOnError: true,
);
$driver = new SymfonyDriver(
    config: $httpConfig,
    clientInstance: SymfonyHttpClient::create(['http_version' => '2.0']),
);
$customClient = (new HttpClient)->withDriver($driver);

$answer = (new Inference)
    ->withConfig($config)
    ->withHttpClient($customClient)
    ->with(
        messages: [['role' => 'user', 'content' => 'What is the capital of France']],
        options: ['max_tokens' => 64]
    )
    ->get();

echo "USER: What is capital of France\n";
echo "ASSISTANT: $answer\n";

assert(Str::contains($answer, 'Paris'));
?>
```
