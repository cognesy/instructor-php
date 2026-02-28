---
title: 'Google Gemini (OpenAI-compatible)'
docname: 'google_gemini_oai'
---

## Overview

Google offers Gemini models which perform well in benchmarks.

Supported modes:
 - OutputMode::MdJson - fallback mode
 - OutputMode::Json - recommended
 - OutputMode::Tools - supported

Here's how you can use Instructor with Gemini API in OpenAI-compatible mode.

```php
<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$http = (new HttpClientBuilder())->withHttpDebugPreset('detailed')->create();

$answer = Inference::fromRuntime(InferenceRuntime::using(
        preset: 'gemini-oai', // use OpenAI-compatible Gemini preset (v1beta/openai)
        httpClient: $http,
    ))
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
