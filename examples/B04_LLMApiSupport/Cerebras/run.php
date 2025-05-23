---
title: 'Cerebras'
docname: 'cerebras'
---

## Overview

Support for Cerebras API which uses custom hardware for super fast inference.
Cerebras provides Llama models.

Mode compatibility:
- OutputMode::Tools (supported)
- OutputMode::Json (supported)
- OutputMode::JsonSchema (supported)
- OutputMode::MdJson (fallback)

## Example

```php
<?php

use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = (new Inference)
    ->using('cerebras') // see /config/llm.php
    ->create(
        messages: [['role' => 'user', 'content' => 'What is the capital of France']],
        options: ['max_tokens' => 64]
    )
    ->toText();

echo "USER: What is capital of France\n";
echo "ASSISTANT: $answer\n";
assert(Str::contains($answer, 'Paris'));
?>
```