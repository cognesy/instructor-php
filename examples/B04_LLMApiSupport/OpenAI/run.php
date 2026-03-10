---
title: 'OpenAI'
docname: 'llm_openai'
id: '2e9b'
---
## Overview

This is the default client used by Instructor.

Inference feature compatibility:
 - tool calling (supported)
 - native JSON object response_format (supported)
 - native JSON schema response_format (recommended for new models)
 - Instructor markdown-JSON fallback (fallback)

## Example

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = Inference::using('openai')
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
