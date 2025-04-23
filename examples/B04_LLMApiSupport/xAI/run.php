---
title: 'xAI / Grok'
docname: 'xai'
---

## Overview

Support for xAI's API, which offers access to X.com's Grok model.

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
    ->withConnection('xai') // see /config/llm.php
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