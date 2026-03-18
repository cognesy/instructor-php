---
title: 'Use custom configuration source'
docname: 'config_providers'
id: '01f2'
tags:
  - 'advanced'
  - 'configuration'
  - 'config-providers'
---
## Overview

This example demonstrates an edge adapter that reads raw config arrays from a custom source
and maps them into typed config objects used by runtime/core classes.

## Example

```php
<?php
require 'examples/boot.php';

use Adbar\Dot;
use Cognesy\Config\Env;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Polyglot\Inference\LLMProvider;

final class CustomConfigSource
{
    private Dot $dot;

    public function __construct(array $data = []) {
        $this->dot = new Dot($data);
    }

    public function llmConnection(string $name): array {
        $value = $this->dot->get("llm.connections.$name");
        if (!is_array($value)) {
            throw new RuntimeException("Unknown LLM connection: $name");
        }
        return $value;
    }
}

$configSource = new CustomConfigSource([
    'llm' => [
        'connections' => [
            'deepseek' => [
                'driver' => 'deepseek',
                'apiUrl' => 'https://api.deepseek.com',
                'apiKey' => (string) Env::get('DEEPSEEK_API_KEY', ''),
                'endpoint' => '/chat/completions',
                'model' => 'deepseek-chat',
                'maxTokens' => 128,
            ],
            'openai' => [
                'driver' => 'openai',
                'apiUrl' => 'https://api.openai.com/v1',
                'apiKey' => (string) Env::get('OPENAI_API_KEY', ''),
                'endpoint' => '/chat/completions',
                'model' => 'gpt-4.1-nano',
                'maxTokens' => 256,
            ],
        ],
    ],
]);

$connection = match (true) {
    (string) Env::get('DEEPSEEK_API_KEY', '') !== '' => 'deepseek',
    (string) Env::get('OPENAI_API_KEY', '') !== '' => 'openai',
    default => throw new RuntimeException('Set DEEPSEEK_API_KEY or OPENAI_API_KEY in your environment to run this example.'),
};

$events = new EventDispatcher();
$httpClient = (new HttpClientBuilder(events: $events))
    ->withConfig(new HttpClientConfig(driver: 'symfony'))
    ->create();

$llmConfig = LLMConfig::fromArray($configSource->llmConnection($connection));
$provider = LLMProvider::fromLLMConfig($llmConfig);

$runtime = StructuredOutputRuntime::fromProvider(
    provider: $provider,
    events: $events,
    httpClient: $httpClient,
)->withOutputMode(OutputMode::Tools);

class User {
    public int $age;
    public string $name;
}

$runtime->wiretap(fn($e) => $e->print());

$structuredOutput = new StructuredOutput($runtime);

$user = $structuredOutput
    ->withMessages('Our user Jason is 25 years old.')
    ->withResponseClass(User::class)
    ->withStreaming()
    ->get();

dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
