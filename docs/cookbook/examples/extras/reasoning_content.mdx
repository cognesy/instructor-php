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
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Utils\Debug\Debug;
use Cognesy\Utils\Str;

//Debug::enable();

// EXAMPLE 1: regular API, allows to customize inference options
$response = (new Inference)
    ->withConnection('deepseek-r') // optional, default is set in /config/llm.php
    ->create(
        messages: [['role' => 'user', 'content' => 'What is the capital of France. Answer with just a name.']],
        options: ['max_tokens' => 64]
    )
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
    ->withConnection('deepseek-r') // optional, default is set in /config/llm.php
    ->create(
        messages: [['role' => 'user', 'content' => 'What is capital of Brasil. Answer with just a name.']],
        options: ['max_tokens' => 128, 'stream' => true]
    )
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
