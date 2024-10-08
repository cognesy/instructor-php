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
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Features\LLM\Inference;
use Cognesy\Instructor\Utils\Str;


// EXAMPLE 1: simplified API, default connection for convenient ad-hoc calls
$answer = Inference::text('What is capital of Germany');

echo "USER: What is capital of Germany\n";
echo "ASSISTANT: $answer\n";
assert(Str::contains($answer, 'Berlin'));




// EXAMPLE 2: regular API, allows to customize inference options
$answer = (new Inference)
    ->withConnection('openai') // optional, default is set in /config/llm.php
    ->create(
        messages: [['role' => 'user', 'content' => 'What is capital of France']],
        options: ['max_tokens' => 64]
    )
    ->toText();

echo "USER: What is capital of France\n";
echo "ASSISTANT: $answer\n";
assert(Str::contains($answer, 'Paris'));




// EXAMPLE 3: streaming response
$stream = (new Inference)
    ->create(
        messages: [['role' => 'user', 'content' => 'Describe capital of Brasil']],
        options: ['max_tokens' => 128, 'stream' => true]
    )
    ->stream();

echo "USER: Describe capital of Brasil\n";
echo "ASSISTANT: ";
foreach ($stream as $partial) {
    echo $partial->contentDelta;
}
echo "\n";
?>
```
