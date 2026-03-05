---
title: 'Debugging HTTP Calls'
docname: 'http_debug'
id: 'f26a'
---
## Overview

Instructor PHP provides a way to debug HTTP calls made to LLM APIs by using
an HTTP client configured with `withDebugConfig(ExampleConfig::debugPreset(...))` and passing it into
`InferenceRuntime`.

When HTTP debug mode is enabled, the HTTP middleware stack prints request and
response details (including streaming data) to the console and dispatches HTTP
debug events.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

$http = (new HttpClientBuilder())->withDebugConfig(ExampleConfig::debugPreset('on'))->create();

$response = Inference::fromRuntime(InferenceRuntime::fromConfig(
        config: ExampleConfig::llmPreset('openai'),
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
