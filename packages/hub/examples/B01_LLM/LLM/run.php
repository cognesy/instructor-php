---
title: 'Working directly with LLMs'
docname: 'inference'
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

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

// EXAMPLE 1: use default LLM connection preset for convenient ad-hoc calls
$answer = (new Inference)
    ->with(messages: 'What is capital of Germany')
    ->get();

echo "USER: What is capital of Germany\n";
echo "ASSISTANT: $answer\n\n";
assert(Str::contains($answer, 'Berlin'));




// EXAMPLE 2: customize inference options using fluent API
$response = (new Inference)
    ->using('openai') // optional, default is set in /config/llm.php
    ->withMessages([['role' => 'user', 'content' => 'What is capital of France']])
    ->withOptions(['max_tokens' => 64])
    ->create();

$answer = $response->get();
echo "USER: What is capital of France\n";
echo "ASSISTANT: $answer\n\n";
assert(Str::contains($answer, 'Paris'));




// EXAMPLE 3: streaming response
$stream = (new Inference)
    ->withMessages([['role' => 'user', 'content' => 'Describe capital of Brasil']])
    ->withOptions(['max_tokens' => 128])
    ->withStreaming()
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
