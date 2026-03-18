---
title: 'Work directly with HTTP client facade'
docname: 'http_client'
id: '6c0f'
tags:
  - 'llm-advanced'
  - 'http-client'
  - 'transport'
---
## Overview

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Config\Dsn;
use Cognesy\Config\Env;
use Cognesy\Http\HttpClient;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Utils\Str;

// check with default HTTP client facade
$httpClient = HttpClient::default();

$openAiApiKey = (string) Env::get('OPENAI_API_KEY', '');
$dsn = "driver=openai,apiUrl=https://api.openai.com/v1,endpoint=/chat/completions,apiKey={$openAiApiKey},model=gpt-4.1-nano";

$answer = Inference::fromRuntime(InferenceRuntime::fromConfig(
    config: LLMConfig::fromArray(Dsn::fromString($dsn)->toArray()),
    httpClient: $httpClient,
))
    ->withMessages(Messages::fromString('What is the capital of France'))
    ->withMaxTokens(64)
    ->get();

echo "USER: What is capital of France\n";
echo "ASSISTANT: $answer\n";

assert(Str::contains($answer, 'Paris'));
?>
```
