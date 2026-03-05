---
title: 'SambaNova'
docname: 'llm_sambanova'
id: '32f9'
---
## Overview

Support for SambaNova's API, which provide fast inference endpoints for Llama and Qwen LLMs.

Mode compatibility:
- OutputMode::MdJson (supported)
- OutputMode::Tools (not supported)
- OutputMode::Json (not supported)
- OutputMode::JsonSchema (not supported)

## Example

```php
<?php

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = Inference::using('sambanova')
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
