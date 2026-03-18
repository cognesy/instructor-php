---
title: 'Load Config From Custom Path'
docname: 'settings'
id: 'cf65'
tags:
  - 'troubleshooting'
  - 'configuration'
  - 'custom-path'
---
## Overview

This example demonstrates edge-level config loading from a custom directory.
Core classes receive typed config objects and never read files directly.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Config\Config;
use Cognesy\Http\Config\DebugConfig;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\LLMProvider;

class UserDetail
{
    public int $age;
    public string $firstName;
    public ?string $lastName;
}

$config = new Config([__DIR__ . '/config']);
$llmData = $config->load('llm/openai.yaml')->toArray();
$debugData = $config->load('debug/on.yaml')->toArray();

$httpClient = (new HttpClientBuilder())
    ->withDebugConfig(DebugConfig::fromArray($debugData))
    ->create();

$provider = LLMProvider::fromLLMConfig(LLMConfig::fromArray($llmData));
$runtime = StructuredOutputRuntime::fromProvider(
    provider: $provider,
    httpClient: $httpClient,
);

$user = (new StructuredOutput($runtime))
    ->withMessages('Jason is 25 years old.')
    ->withResponseClass(UserDetail::class)
    ->get();

dump($user);

assert(!isset($user->lastName) || $user->lastName === '');
?>
```
