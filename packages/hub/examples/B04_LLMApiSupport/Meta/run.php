---
title: 'Meta'
docname: 'meta'
---

## Overview

Instructor supports Meta LLM inference API. You can find the details on how to configure

## Example

```php
<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$http = (new HttpClientBuilder())->withHttpDebugPreset('on')->create();

$answer = Inference::fromRuntime(InferenceRuntime::using(
        preset: 'meta', // see /config/llm.php
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
