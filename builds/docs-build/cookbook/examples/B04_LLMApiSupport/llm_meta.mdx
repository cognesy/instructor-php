---
title: 'Meta'
docname: 'llm_meta'
id: '2dca'
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
use Cognesy\Polyglot\Inference\Config\LLMConfig;

require 'examples/boot.php';

$http = (new HttpClientBuilder())->withDebugConfig(ExampleConfig::debugPreset('on'))->create();

$answer = Inference::fromRuntime(InferenceRuntime::fromConfig(
        config: ExampleConfig::llmPreset('meta'), // adjust values directly in LLMConfig::fromArray([...])
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
