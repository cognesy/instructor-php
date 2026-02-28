---
title: 'Debugging HTTP Calls'
docname: 'http_debug'
---

## Overview

Instructor PHP provides a way to debug HTTP calls made to LLM APIs by using
an HTTP client configured with `withHttpDebugPreset()` and passing it into
`InferenceRuntime`.

When debug mode is turned on all HTTP requests and responses are dumped to the console.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Http\Creation\HttpClientBuilder;

$http = (new HttpClientBuilder())->withHttpDebugPreset('on')->create();

$response = Inference::fromRuntime(InferenceRuntime::using(
        preset: 'openai',
        httpClient: $http,
    ))
    ->with(
        messages: [['role' => 'user', 'content' => 'What is the capital of Brasil']],
        options: ['max_tokens' => 128]
    )
    ->get();

echo "USER: What is capital of Brasil\n";
echo "ASSISTANT: $response\n";
?>
```
