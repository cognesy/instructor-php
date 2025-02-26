---
title: 'Mistral AI'
docname: 'mistralai'
---

## Overview

Mistral.ai is a company that builds OS language models, but also offers a platform
hosting those models. You can use Instructor with Mistral API by configuring the
client as demonstrated below.

Please note that the larger Mistral models support Mode::Json, which is much more
reliable than Mode::MdJson.

Mode compatibility:
 - Mode::Tools - supported (Mistral-Small / Mistral-Medium / Mistral-Large)
 - Mode::Json - recommended (Mistral-Small / Mistral-Medium / Mistral-Large)
 - Mode::MdJson - fallback mode (Mistral 7B / Mixtral 8x7B)

## Example

```php
<?php

use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = (new Inference)
    ->withConnection('mistral') // see /config/llm.php
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