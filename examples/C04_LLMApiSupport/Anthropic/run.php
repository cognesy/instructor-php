---
title: 'Anthropic'
docname: 'anthropic'
---

## Overview

Instructor supports Anthropic API - you can find the details on how to configure
the client in the example below.

Mode compatibility:
- Mode::MdJson, Mode::Json - supported
- Mode::Tools - not supported yet


## Example

```php
<?php

use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = (new Inference)
    ->withConnection('anthropic') // see /config/llm.php
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