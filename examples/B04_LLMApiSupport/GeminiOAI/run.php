---
title: 'Google Gemini (OpenAI-compatible)'
docname: 'llm_google_gemini_oai'
id: '821d'
---
## Overview

Google offers Gemini models which perform well in benchmarks.

Supported modes:
 - Instructor markdown-JSON fallback - fallback mode
 - native JSON object response_format - recommended
 - tool calling - supported

Here's how you can use Instructor with Gemini API in OpenAI-compatible mode.

```php
<?php

use Cognesy\Http\Config\DebugConfig;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$http = (new HttpClientBuilder)->withDebugConfig(DebugConfig::fromPreset('detailed'))->create();

$answer = Inference::fromRuntime(InferenceRuntime::fromConfig(
    config: LLMConfig::fromPreset('gemini-oai'), // use OpenAI-compatible Gemini config (v1beta/openai)
    httpClient: $http,
))
    ->with(
        messages: Messages::fromString('What is the capital of France'),
        options: ['max_tokens' => 64]
    )
    ->get();

echo "USER: What is capital of France\n";
echo "ASSISTANT: $answer\n";
assert(Str::contains($answer, 'Paris'));
?>
```
