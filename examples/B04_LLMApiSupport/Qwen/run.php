---
title: 'Qwen'
docname: 'llm_qwen'
id: 'f71c'
tags:
  - 'llm-api-support'
  - 'qwen'
  - 'provider'
---
## Overview

QwenCloud exposes an OpenAI-compatible Chat Completions API. The bundled
`qwen` provider uses the DashScope international endpoint and the
`QWEN_API_KEY` environment variable.

Inference feature compatibility:
- tool calling (supported; `required` is sent as `auto`)
- native JSON object response_format (supported)
- native JSON Schema response_format (supported by Qwen3.8-Max and selected
  Qwen3.7 models; older models degrade to JSON object)
- thinking controls (`enable_thinking` and `reasoning_effort`) (supported)
- Instructor markdown-JSON fallback (fallback)

## Example

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = Inference::using('qwen')
    ->with(
        messages: Messages::fromString('What is the capital of France?'),
        options: [
            'enable_thinking' => false,
            'max_tokens' => 64,
        ],
    )
    ->get();

echo "USER: What is the capital of France?\n";
echo "ASSISTANT: $answer\n";
assert(Str::contains($answer, 'Paris'));
?>
```
