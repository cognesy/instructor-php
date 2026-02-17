---
title: 'Reasoning Content Access'
docname: 'reasoning_content'
path: ''
---

## Overview

Deepseek API allows to access reasoning content, which is a detailed explanation of how the response was generated.
This feature is useful for debugging and understanding the reasoning behind the response.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

// EXAMPLE 1: regular API, allows to customize inference options
$response = (new Inference)
    //->withHttpDebugPreset('on')
    //->wiretap(fn($e) => $e->print())
    ->using('deepseek-r')
    ->withMessages([['role' => 'user', 'content' => 'What is the capital of France. Answer with just a name.']])
    ->withMaxTokens(256)
    ->response();

echo "\nCASE #1: Sync response\n";
echo "USER: What is capital of France\n";
echo "ASSISTANT: {$response->content()}\n";
echo "REASONING: {$response->reasoningContent()}\n";
assert($response->content() !== '');
assert(Str::contains($response->content(), 'Paris'));
assert($response->reasoningContent() !== '');


// EXAMPLE 2: streaming response
$stream = (new Inference)
    //->withHttpDebugPreset('on')
    //->wiretap(fn($e) => $e->print())
    ->using('deepseek-r') // optional, default is set in /config/llm.php
    ->with(
        messages: [['role' => 'user', 'content' => 'What is capital of Brasil. Answer with just a name.']],
        options: ['max_tokens' => 256]
    )
    ->withStreaming()
    ->stream();

echo "\nCASE #2: Streamed response\n";
echo "USER: What is capital of Brasil\n";
echo "ASSISTANT: ";
foreach ($stream->responses() as $partial) {
    echo $partial->contentDelta;
}
echo "\n";
echo "REASONING: {$stream->final()->reasoningContent()}\n";
assert($stream->final()->reasoningContent() !== '');
assert($stream->final()->content() !== '');
assert(Str::contains($stream->final()->content(), 'Brasília'));
?>
```
