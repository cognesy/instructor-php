---
title: 'Local / Ollama'
docname: 'ollama'
---

## Overview

You can use Instructor with local Ollama instance.

Please note that, at least currently, OS models do not perform on par with OpenAI (GPT-3.5 or GPT-4) model for complex data schemas.

Supported modes:
 - OutputMode::MdJson - fallback mode, works with any capable model
 - OutputMode::Json - recommended
 - OutputMode::Tools - supported (for selected models - check Ollama docs)

## Example

```php
\<\?php

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = (new Inference)
    ->using('ollama') // see /config/llm.php
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