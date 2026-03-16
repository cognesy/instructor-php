---
title: 'SambaNova'
docname: 'llm_sambanova'
id: '32f9'
---
## Overview

Support for SambaNova's API, which provide fast inference endpoints for Llama and Qwen LLMs.

Inference feature compatibility:
- Instructor markdown-JSON fallback (supported)
- tool calling (not supported)
- native JSON object response_format (not supported)
- native JSON schema response_format (not supported)

## Example

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = Inference::using('sambanova')
    ->with(
        messages: Messages::fromString('What is the capital of France'),
        options: ['max_tokens' => 64]
    )
    ->get();

echo "USER: What is capital of France\n";
echo "ASSISTANT: $answer\n";
assert(Str::contains($answer, 'Paris'));
?>
```
