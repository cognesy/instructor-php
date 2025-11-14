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

use Cognesy\Config\Env;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIDriver;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

// we will use existing, bundled driver as an example, but you can provide any class that implements
// a required interface (CanHandleInference)

Inference::registerDriver(
    name: 'custom-driver',
    driver: fn($config, $httpClient, $events) => new class($config, $httpClient, $events) extends OpenAIDriver {
        #[\Override]
        protected function makeHttpResponse(\Cognesy\Http\Data\HttpRequest $request): HttpResponse {
            // some extra functionality to demonstrate our driver is being used
            echo ">>> Handling request...\n";
            return parent::makeHttpResponse($request);
        }
    }
);

// Create instance of LLM client initialized with custom parameters
$config = new LLMConfig(
    apiUrl          : 'https://api.openai.com/v1',
    apiKey          : Env::get('OPENAI_API_KEY'),
    endpoint        : '/chat/completions', model: 'gpt-4o-mini', maxTokens: 128,
    driver          : 'custom-driver',
);

$answer = (new Inference)
    ->withConfig($config)
    ->withMessages([['role' => 'user', 'content' => 'What is the capital of France']])
    ->withOptions(['max_tokens' => 64])
    ->get();

echo "USER: What is capital of France\n";
echo "ASSISTANT: $answer\n";

assert(Str::contains($answer, 'Paris'));
?>
```
