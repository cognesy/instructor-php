---
title: 'SambaNova'
docname: 'sambanova'
---

## Overview

Support for SambaNova's API, which provide fast inference endpoints for Llama and Qwen LLMs.

Mode compatibility:
- OutputMode::MdJson (supported)
- OutputMode::Tools (not supported)
- OutputMode::Json (not supported)
- OutputMode::JsonSchema (not supported)

## Example

```php
<?php

use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = (new Inference)
    ->using('sambanova') // see /config/llm.php
    ->with(
        messages: [['role' => 'user', 'content' => 'What is the capital of France']],
        options: ['max_tokens' => 64]
    )
    ->toText();

echo "USER: What is capital of France\n";
echo "ASSISTANT: $answer\n";
assert(Str::contains($answer, 'Paris'));
?>
```