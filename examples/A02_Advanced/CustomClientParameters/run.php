---
title: 'Customize parameters of LLM driver'
docname: 'custom_llm'
---

## Overview

You can provide your own LLM configuration instance to Instructor. This is useful
when you want to initialize OpenAI client with custom values - e.g. to call
other LLMs which support OpenAI API.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\Instructor;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Env;

class User {
    public int $age;
    public string $name;
}

// Create instance of LLM client initialized with custom parameters
$config = new LLMConfig(
    apiUrl: 'https://api.deepseek.com',
    apiKey: Env::get('DEEPSEEK_API_KEY'),
    endpoint: '/chat/completions',
    model: 'deepseek-chat',
    maxTokens: 128,
    httpClient: 'guzzle',
    providerType: 'openai-compatible',
);

// Get Instructor with the default client component overridden with your own
$instructor = (new Instructor)->withLLMConfig($config);

// Call with custom model and execution mode
$user = $instructor->respond(
    messages: "Our user Jason is 25 years old.",
    responseModel: User::class,
    mode: OutputMode::Tools,
);


dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
