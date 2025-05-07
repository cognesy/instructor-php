---
title: 'Using custom LLM driver'
docname: 'custom_llm_driver'
---

## Overview

You can register and use your own LLM driver, either using a new driver name
or overriding an existing driver bundled with Polyglot.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Embeddings\Drivers\OpenAIDriver;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Polyglot\LLM\LLM;
use Cognesy\Utils\Env;
use Cognesy\Utils\Str;

class CustomDriver extends OpenAIDriver {}

// we will use existing, bundled driver as an example, but you can provide any class that implements
// a required interface (CanHandleInference)
LLM::registerDriver('custom-driver', $driver);

// Create instance of LLM client initialized with custom parameters
$config = new LLMConfig(
    apiUrl: 'https://api.openai.com/v1',
    apiKey: Env::get('OPENAI_API_KEY'),
    endpoint: '/chat/completions',
    model: 'gpt-4o-mini',
    maxTokens: 128,
    httpClient: 'guzzle',
    providerType: 'custom-driver',
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
