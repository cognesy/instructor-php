---
title: 'OpenAI Responses API'
docname: 'openai-responses'
---

## Overview

OpenAI's Responses API is their new recommended API for inference, offering improved
performance and features compared to Chat Completions.

Key features:
- 3% better performance on reasoning tasks
- 40-80% improved cache utilization
- Built-in tools: web search, file search, code interpreter
- Server-side conversation state via `previous_response_id`
- Semantic streaming events

Mode compatibility:
 - OutputMode::Tools (supported)
 - OutputMode::Json (supported)
 - OutputMode::JsonSchema (recommended)
 - OutputMode::MdJson (fallback)

## Example

```php
<?php

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = (new Inference)
    ->using('openai-responses') // see /config/llm.php
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
