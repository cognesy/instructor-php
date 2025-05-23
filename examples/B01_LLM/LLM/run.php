---
title: 'Working directly with LLMs'
docname: 'llm'
---

## Overview

`Inference` class offers access to LLM APIs and convenient methods to execute
model inference, incl. chat completions, tool calling or JSON output
generation.

LLM providers access details can be found and modified via
`/config/llm.php`.


## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Utils\Str;

// EXAMPLE 1: simplified API, default LLM connection preset for convenient ad-hoc calls
$answer = Inference::text('What is capital of Germany');

echo "USER: What is capital of Germany\n";
echo "ASSISTANT: $answer\n\n";
assert(Str::contains($answer, 'Berlin'));




// EXAMPLE 2: regular API, allows to customize inference options
$answer = (new Inference)
    ->using('openai') // optional, default is set in /config/llm.php
    ->create(
        messages: [['role' => 'user', 'content' => 'What is capital of France']],
        options: ['max_tokens' => 64]
    )
    ->toText();

echo "USER: What is capital of France\n";
echo "ASSISTANT: $answer\n\n";
assert(Str::contains($answer, 'Paris'));




// EXAMPLE 3: streaming response
$stream = (new Inference)
    ->create(
        messages: [['role' => 'user', 'content' => 'Describe capital of Brasil']],
        options: ['max_tokens' => 128, 'stream' => true]
    )
    ->stream()
    ->responses();

echo "USER: Describe capital of Brasil\n";
echo "ASSISTANT: ";
foreach ($stream as $partial) {
    echo $partial->contentDelta;
}
echo "\n";
?>
```
