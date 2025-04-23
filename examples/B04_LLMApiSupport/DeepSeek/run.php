---
title: 'DeepSeek'
docname: 'deepseek'
---

## Overview

Support for DeepSeek API which provides strong models at affordable price.

Mode compatibility:
- Mode::Tools (supported)
- Mode::Json (supported)
- Mode::JsonSchema (supported)
- Mode::MdJson (fallback)

## Example

```php
<?php

use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = (new Inference)
    ->withConnection('deepseek') // see /config/llm.php
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