---
title: 'Debugging HTTP Calls'
docname: 'http_debug'
id: 'f26a'
---
## Overview

Instructor PHP provides a way to debug HTTP calls made to LLM APIs by using
an HTTP client configured with `withDebugConfig(DebugConfig::fromPreset(...))` and passing it into
`InferenceRuntime`.

When HTTP debug mode is enabled, the HTTP middleware stack prints request and
response details (including streaming data) to the console and dispatches HTTP
debug events.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Http\Config\DebugConfig;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;

$http = (new HttpClientBuilder)->withDebugConfig(DebugConfig::fromPreset('on'))->create();

$response = Inference::fromRuntime(InferenceRuntime::fromConfig(
    config: LLMConfig::fromPreset('openai'),
    httpClient: $http,
))
    ->with(
        messages: Messages::fromString('What is the capital of Brasil?'),
        options: ['max_tokens' => 128]
    )
    ->get();

echo "USER: What is capital of Brasil\n";
echo "ASSISTANT: $response\n";

assert(!empty($response));
?>
```
