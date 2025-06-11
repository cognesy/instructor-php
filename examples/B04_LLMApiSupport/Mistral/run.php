---
title: 'Mistral AI'
docname: 'mistralai'
---

## Overview

Mistral.ai is a company that builds OS language models, but also offers a platform
hosting those models. You can use Instructor with Mistral API by configuring the
client as demonstrated below.

Please note that the larger Mistral models support OutputMode::Json, which is much more
reliable than OutputMode::MdJson.

Mode compatibility:
 - OutputMode::Tools - supported (Mistral-Small / Mistral-Medium / Mistral-Large)
 - OutputMode::Json - recommended (Mistral-Small / Mistral-Medium / Mistral-Large)
 - OutputMode::MdJson - fallback mode (Mistral 7B / Mixtral 8x7B)

## Example

```php
<?php

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = (new Inference)
    ->using('mistral') // see /config/llm.php
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