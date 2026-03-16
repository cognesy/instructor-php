---
title: 'Groq'
docname: 'llm_groq'
id: '7c03'
---
## Overview

Groq is LLM providers offering a very fast inference thanks to their
custom hardware. They provide a several models - Llama2, Mixtral and Gemma.

Supported modes depend on the specific model, but generally include:
 - Instructor markdown-JSON fallback - fallback mode
 - native JSON object response_format - recommended
 - tool calling - supported

Here's how you can use Instructor with Groq API.

## Example

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = Inference::using('groq')
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
