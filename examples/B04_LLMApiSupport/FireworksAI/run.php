---
title: 'Fireworks.ai'
docname: 'llm_fireworks'
id: 'dc10'
tags:
  - 'llm-api-support'
  - 'fireworks'
  - 'provider'
---
## Overview

Please note that the larger Mistral models support native JSON object response_format, which is much more
reliable than Instructor markdown-JSON fallback.

Inference feature compatibility:
- tool calling - selected models
- native JSON object response_format - selected models
- Instructor markdown-JSON fallback


## Example

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = Inference::using('fireworks')
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
