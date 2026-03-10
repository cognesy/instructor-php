---
title: 'Cerebras'
docname: 'llm_cerebras'
id: '134c'
---
## Overview

Support for Cerebras API which uses custom hardware for super fast inference.
Cerebras provides Llama models.

Inference feature compatibility:
- tool calling (supported)
- native JSON object response_format (supported)
- native JSON schema response_format (supported)
- Instructor markdown-JSON fallback (fallback)

## Example

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = Inference::using('cerebras')
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
