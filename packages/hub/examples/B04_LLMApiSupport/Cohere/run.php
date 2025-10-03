---
title: 'Cohere'
docname: 'cohere'
---

## Overview

Instructor supports Cohere API - you can find the details on how to configure
the client in the example below.

Mode compatibility:
 - OutputMode::MdJson - supported, recommended as a fallback from JSON mode
 - OutputMode::Json - supported, recommended
 - OutputMode::Tools - partially supported, not recommended

Reasons OutputMode::Tools is not recommended:

 - Cohere does not support JSON Schema, which only allows to extract very simple, flat data schemas.
 - Performance of the currently available versions of Cohere models in tools mode for Instructor use case (data extraction) is extremely poor.


## Example

```php
<?php

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = (new Inference)
    ->using('cohere') // see /config/llm.php
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