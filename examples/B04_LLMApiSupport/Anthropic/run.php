---
title: 'Anthropic'
docname: 'anthropic'
---

## Overview

Instructor supports Anthropic API - you can find the details on how to configure
the client in the example below.

Mode compatibility:
- OutputMode::MdJson, OutputMode::Json - supported
- OutputMode::Tools - not supported yet


## Example

```php
<?php

use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = (new Inference)
    ->using('anthropic') // see /config/llm.php
    ->withDebugPreset('detailed')
    //->wiretap(fn($e) => $e->print())
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