---
title: 'Cohere'
docname: 'llm_cohere'
id: '9331'
tags:
  - 'llm-api-support'
  - 'cohere'
  - 'provider'
---
## Overview

Instructor supports Cohere API - you can find the details on how to configure
the client in the example below.

Inference feature compatibility:
 - Instructor markdown-JSON fallback - supported, recommended as a fallback from JSON mode
 - native JSON object response_format - supported, recommended
 - tool calling - partially supported, not recommended

Reasons tool calling is not recommended:

 - Cohere does not support JSON Schema, which only allows to extract very simple, flat data schemas.
 - Performance of the currently available versions of Cohere models in tools mode for Instructor use case (data extraction) is extremely poor.


## Example

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = Inference::using('cohere')
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
