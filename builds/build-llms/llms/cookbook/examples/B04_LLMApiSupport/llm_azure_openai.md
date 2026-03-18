---
title: 'Azure OpenAI'
docname: 'llm_azure_openai'
id: '50a3'
tags:
  - 'llm-api-support'
  - 'azure-openai'
  - 'provider'
---
## Overview

You can connect to Azure OpenAI instance using a dedicated client provided
by Instructor. Please note it requires setting up your own model deployment
using Azure OpenAI service console.


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
