---
title: 'Together.ai'
docname: 'togetherai'
---

## Overview

Together.ai hosts a number of language models and offers inference API with support for
chat completion, JSON completion, and tools call. You can use Instructor with Together.ai
as demonstrated below.

Please note that some Together.ai models support Mode::Tools or Mode::Json, which are much
more reliable than Mode::MdJson.

Mode compatibility:
- Mode::Tools - supported for selected models
- Mode::Json - supported for selected models
- Mode::MdJson - fallback mode


## Example

```php
<?php

use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = (new Inference)
    ->withConnection('together') // see /config/llm.php
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