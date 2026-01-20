---
title: 'Debugging'
docname: 'debugging'
---

## Overview

The `StructuredOutput` class has a `withDebug()` method that can be used to debug the request and response.

It displays detailed information about the request being sent to LLM API and response received from it,
including:
 - request headers, URI, method and body,
 - response status, headers, and body.

This is useful for debugging the request and response when you are not getting the expected results.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Utils\Str;

class User {
    public int $age;
    public string $name;
}

// CASE 1.1 - normal flow, sync request

$structuredOutput = (new StructuredOutput)
    ->using('openai')
    ->withDebugPreset('on');
    //->wiretap(fn($e) => $e->print());

echo "\n### CASE 1.1 - Debugging sync request\n\n";
$user = $structuredOutput
    ->with(
        messages: "Jason is 25 years old.",
        responseModel: User::class,
        options: [ 'stream' => false ]
    )
    ->get();

echo "\nResult:\n";
assert(isset($user->name));
assert(isset($user->age));
assert($user->name === 'Jason');
assert($user->age === 25);

// CASE 1.2 - normal flow, streaming request

echo "\n### CASE 1.2 - Debugging streaming request\n\n";
$user2 = $structuredOutput
    ->with(
        messages: "Anna is 21 years old.",
        responseModel: User::class,
        options: [ 'stream' => true ]
    )
    ->get();

echo "\nResult:\n";
dump($user2);

assert(isset($user2->name));
assert(isset($user2->age));
assert($user2->name === 'Anna');
assert($user2->age === 21);


// CASE 2 - forcing API error via empty LLM config

// let's initialize the instructor with an incorrect LLM config
$structuredOutput = (new StructuredOutput)
    ->withLLMConfig(new LLMConfig(apiUrl: 'https://example.com'));

echo "\n### CASE 2 - Debugging with HTTP exception\n\n";
try {
    $user = $structuredOutput
        ->withDebugPreset('on')
        ->with(
            messages: "Jason is 25 years old.",
            responseModel: User::class,
            options: [ 'stream' => true ]
        )
        ->get();
} catch (Exception $e) {
    $msg = Str::limit($e->getMessage(), 250);
    echo "EXCEPTION WE EXPECTED:\n";
    echo "\nCaught exception: " . $msg . "\n";
}
?>
```
