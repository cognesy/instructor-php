---
title: 'DeepSeek'
docname: 'deepseek'
---

## Overview

Support for DeepSeek API which provides strong models at affordable price.

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
    ->using('deepseek') // see /config/llm.php
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