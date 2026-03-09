---
title: 'Reasoning Content Access'
docname: 'reasoning_content'
path: ''
id: '0b56'
---
## Overview

Deepseek API allows to access reasoning content, which is a detailed explanation of how the response was generated.
This feature is useful for debugging and understanding the reasoning behind the response.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Inference\Inference;

// EXAMPLE 1: regular API, allows to customize inference options
$response = Inference::using('deepseek-r')
    ->withMessages([['role' => 'user', 'content' => 'What is the capital of France. Answer with just a name.']])
    ->withMaxTokens(256)
    ->response();

echo "\nCASE #1: Sync response\n";
echo "USER: What is capital of France\n";
echo "ASSISTANT: {$response->content()}\n";
echo "REASONING: {$response->reasoningContent()}\n";
assert($response->content() !== '');
assert($response->reasoningContent() !== '');


// EXAMPLE 2: streaming response
$stream = Inference::using('deepseek-r')
    ->with(
        messages: [['role' => 'user', 'content' => 'What is capital of Brasil. Answer with just a name.']],
        options: ['max_tokens' => 256]
    )
    ->withStreaming()
    ->stream();

echo "\nCASE #2: Streamed response\n";
echo "USER: What is capital of Brasil\n";
echo "ASSISTANT: ";
foreach ($stream->deltas() as $delta) {
    echo $delta->contentDelta;
}
echo "\n";
echo "REASONING: {$stream->final()->reasoningContent()}\n";
assert($stream->final()->reasoningContent() !== '');
assert($stream->final()->content() !== '');
?>
```
