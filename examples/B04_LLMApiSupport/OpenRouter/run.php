---
title: 'OpenRouter'
docname: 'llm_openrouter'
id: 'e2ef'
---
## Overview

You can use Instructor with OpenRouter API. OpenRouter provides easy, unified access
to multiple open source and commercial models. Read OpenRouter docs to learn more about
the models they support.

Please note that OS models are in general weaker than OpenAI ones, which may result in
lower quality of responses or extraction errors. You can mitigate this (partially) by using
validation and `maxRetries` option to make Instructor automatically reattempt the extraction
in case of extraction issues.


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
    config: LLMConfig::fromPreset('openrouter'),
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
