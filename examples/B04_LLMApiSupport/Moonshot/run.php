---
title: 'MoonshotAI'
docname: 'moonshotai'
---

## Overview

Support for MoonshotAI's API.

Mode compatibility:
- OutputMode::MdJson (supported)
- OutputMode::Tools (supported)
- OutputMode::Json (supported)
- OutputMode::JsonSchema (not supported)

## Example

```php
\<\?php

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = (new Inference)
    ->using('moonshot-kimi') // see /config/llm.php
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