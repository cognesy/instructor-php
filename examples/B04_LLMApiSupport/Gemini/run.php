---
title: 'Google Gemini'
docname: 'google_gemini'
---

## Overview

Google offers Gemini models which perform well in benchmarks.

Supported modes:
 - OutputMode::MdJson - fallback mode
 - OutputMode::Json - recommended
 - OutputMode::Tools - supported

Here's how you can use Instructor with Gemini API.

```php
<?php

use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = (new Inference)
    ->using('gemini') // see /config/llm.php
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