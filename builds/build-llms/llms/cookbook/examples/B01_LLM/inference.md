---
title: 'Working directly with LLMs'
docname: 'inference'
id: 'e986'
tags:
  - 'llm'
  - 'text-inference'
  - 'direct-api'
---
## Overview

`Inference` class offers access to LLM APIs and convenient methods to execute
model inference, incl. chat completions, tool calling or JSON output
generation.

LLM providers access details can be found and modified via
`LLMConfig` objects.


## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

// EXAMPLE 1: use default runtime configuration for convenient ad-hoc calls
$answer = Inference::using('openai')
    ->with(messages: Messages::fromString('What is capital of Germany'))
    ->get();

echo "USER: What is capital of Germany\n";
echo "ASSISTANT: $answer\n\n";
assert(Str::contains($answer, 'Berlin'));

// EXAMPLE 2: customize inference options using fluent API
$response = Inference::using('openai')
    ->withMessages(Messages::fromString('What is capital of France'))
    ->withOptions(['max_tokens' => 64])
    ->create();

$answer = $response->get();
echo "USER: What is capital of France\n";
echo "ASSISTANT: $answer\n\n";
assert(Str::contains($answer, 'Paris'));

// EXAMPLE 3: streaming response
$stream = Inference::using('openai')
    ->withMessages(Messages::fromString('Describe capital of Brasil'))
    ->withOptions(['max_tokens' => 128])
    ->withStreaming()
    ->stream()
    ->deltas();

echo "USER: Describe capital of Brasil\n";
echo 'ASSISTANT: ';
foreach ($stream as $delta) {
    echo $delta->contentDelta;
}
echo "\n";
?>
```
