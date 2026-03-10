---
title: 'A21'
docname: 'llm_a21'
id: '1dd0'
---
## Overview

Support for A21 Jamba - MAMBA architecture models, very strong at handling
long context.

## Example

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = Inference::using('a21')
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
