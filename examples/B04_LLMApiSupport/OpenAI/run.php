---
title: 'OpenAI'
docname: 'openai'
---

## Overview

This is the default client used by Instructor.

Mode compatibility:
 - OutputMode::Tools (supported)
 - OutputMode::Json (supported)
 - OutputMode::JsonSchema (recommended for new models)
 - OutputMode::MdJson (fallback)

## Example

```php
<?php

use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = (new Inference)
    ->using('openai') // see /config/llm.php
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