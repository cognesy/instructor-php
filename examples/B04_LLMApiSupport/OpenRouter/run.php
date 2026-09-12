---
title: 'OpenRouter'
docname: 'llm_openrouter'
id: 'e2ef'
tags:
  - 'llm-api-support'
  - 'openrouter'
  - 'provider'
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

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = Inference::using('openrouter')
    ->with(
        messages: Messages::fromString('What is the capital of France'),
        options: ['max_tokens' => 64]
    )
    ->get()->content()->toString();

echo "USER: What is capital of France\n";
echo "ASSISTANT: $answer\n";
assert(Str::contains($answer, 'Paris'));
?>
```
