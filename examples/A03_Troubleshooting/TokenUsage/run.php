---
title: 'Tracking token usage via events'
docname: 'token_usage_events'
---

## Overview

Some use cases require tracking the token usage of the API responses.
This can be done by getting `Usage` object from Instructor LLM response
object.

Code below demonstrates how it can be retrieved for both sync and
streamed requests.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\LLM\Data\Usage;

class User {
    public int $age;
    public string $name;
}

function printUsage(Usage $usage) : void {
    echo "Input tokens: $usage->inputTokens\n";
    echo "Output tokens: $usage->outputTokens\n";
    echo "Cache creation tokens: $usage->cacheWriteTokens\n";
    echo "Cache read tokens: $usage->cacheReadTokens\n";
    echo "Reasoning tokens: $usage->reasoningTokens\n";
}

echo "COUNTING TOKENS FOR SYNC RESPONSE\n";
$text = "Jason is 25 years old and works as an engineer.";
$response = (new StructuredOutput)
    ->with(
        messages: $text,
        responseModel: User::class,
    )->response();

echo "\nTEXT: $text\n";
assert($response->usage()->total() > 0);
printUsage($response->usage());


echo "\n\nCOUNTING TOKENS FOR STREAMED RESPONSE\n";
$text = "Anna is 19 years old.";
$stream = (new StructuredOutput)
    ->with(
        messages: $text,
        responseModel: User::class,
        options: ['stream' => true],
    )
    ->stream();

$response = $stream->finalValue();
echo "\nTEXT: $text\n";
assert($stream->usage()->total() > 0);
printUsage($stream->usage());
?>
```
