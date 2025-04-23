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

use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Enums\LLMProviderType;
use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Utils\Env;
use Cognesy\Utils\Str;

// Create instance of LLM client initialized with custom parameters
$config = new LLMConfig(
    apiUrl: 'https://api.deepseek.com',
    apiKey: Env::get('DEEPSEEK_API_KEY'),
    endpoint: '/chat/completions',
    model: 'deepseek-chat',
    maxTokens: 128,
    httpClient: 'guzzle',
    providerType: LLMProviderType::OpenAICompatible->value,
);

$answer = (new Inference)
    ->withConfig($config)
    ->create(
        messages: [['role' => 'user', 'content' => 'What is the capital of France']],
        options: ['max_tokens' => 64]
    )
    ->toText();

echo "USER: What is capital of France\n";
echo "ASSISTANT: $answer\n";

assert(Str::contains($answer, 'Paris'));
?>
```
