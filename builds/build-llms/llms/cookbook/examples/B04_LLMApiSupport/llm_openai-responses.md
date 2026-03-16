---
title: 'OpenAI Responses API'
docname: 'llm_openai-responses'
id: '653d'
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

Inference feature compatibility:
 - tool calling (supported)
 - native JSON object response_format (supported)
 - native JSON schema response_format (recommended)
 - Instructor markdown-JSON fallback (fallback)

## Example

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = Inference::using('openai-responses')
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
