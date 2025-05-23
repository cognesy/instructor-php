---
title: 'Azure OpenAI'
docname: 'azure_openai'
---

## Overview

You can connect to Azure OpenAI instance using a dedicated client provided
by Instructor. Please note it requires setting up your own model deployment
using Azure OpenAI service console.


## Example

```php
<?php

use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = (new Inference)
    ->using('openai') // see /config/llm.php
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