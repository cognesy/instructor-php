---
title: 'Minimaxi'
docname: 'minimaxi'
---

## Overview

Support for Minimaxi's API.

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
    ->using('minimaxi') // see /config/llm.php
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