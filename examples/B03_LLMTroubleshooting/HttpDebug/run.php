---
title: 'Debugging HTTP Calls'
docname: 'http_debug'
---

## Overview

Instructor PHP provides a way to debug HTTP calls made to LLM APIs via `withDebug()` method call
on `Inference` object.

When debug mode is turned on all HTTP requests and responses are dumped to the console.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\LLM\Inference;

$response = (new Inference)
    ->withDebug() // Enable debug mode
    ->with(
        messages: [['role' => 'user', 'content' => 'What is the capital of Brasil']],
        options: ['max_tokens' => 128]
    )
    ->toText();

echo "USER: What is capital of Brasil\n";
echo "ASSISTANT: $response\n";
?>
```
