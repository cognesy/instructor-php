---
title: 'Customize LLM Configuration with DSN string'
docname: 'custom_llm_via_dsn'
---

## Overview

You can provide your own LLM configuration data to `Inference` object with DSN string.
This is useful for inline configuration or for building configuration from admin UI,
CLI arguments or environment variables.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

$answer = (new Inference)
    ->withDsn('preset=xai,model=grok-3')
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
