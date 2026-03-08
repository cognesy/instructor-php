---
title: 'Together.ai'
docname: 'llm_togetherai'
id: '84d0'
---
## Overview

Together.ai hosts a number of language models and offers inference API with support for
chat completion, JSON completion, and tools call. You can use Instructor with Together.ai
as demonstrated below.

Please note that some Together.ai models support tool calling or native JSON object response_format, which are much
more reliable than Instructor markdown-JSON fallback.

Inference feature compatibility:
- tool calling - supported for selected models
- native JSON object response_format - supported for selected models
- Instructor markdown-JSON fallback - fallback mode


## Example

```php
<?php

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = Inference::using('together')
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
