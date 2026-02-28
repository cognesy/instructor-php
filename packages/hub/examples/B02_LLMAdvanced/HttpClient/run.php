---
title: 'Work directly with HTTP client facade'
docname: 'http_client'
---

## Overview

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Utils\Str;

// check with default HTTP client facade
$httpClient = new HttpClient();

$answer = Inference::fromRuntime(InferenceRuntime::fromDsn(
        dsn: 'preset=openai,model=gpt-3.5-turbo',
        httpClient: $httpClient,
    ))
    ->withMessages('What is the capital of France')
    ->withMaxTokens(64)
    ->get();

echo "USER: What is capital of France\n";
echo "ASSISTANT: $answer\n";

assert(Str::contains($answer, 'Paris'));
?>
```
