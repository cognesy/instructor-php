---
title: 'Meta'
docname: 'llm_meta'
id: '2dca'
skip: true
---
## Overview

Instructor supports Meta LLM inference API. You can find the details on how to configure

## Example

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

$http = (new HttpClientBuilder)->withDebugConfig(DebugConfig::fromPreset('on'))->create();

$answer = Inference::fromRuntime(InferenceRuntime::fromConfig(
    config: LLMConfig::fromPreset('meta'),
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
