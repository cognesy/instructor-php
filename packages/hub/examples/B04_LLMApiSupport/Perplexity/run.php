---
title: 'Perplexity'
docname: 'perplexity'
---

## Overview

Perplexity is a search engine that provides an API for generating text. It is designed to
be used in a variety of applications, including chatbots, content generation, and more.

## Example

```php
\<\?php

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

require 'examples/boot.php';

$answer = (new Inference)
    ->using('perplexity') // see /config/llm.php
    ->with(
        messages: [['role' => 'user', 'content' => 'What is the capital of France']],
        options: ['max_tokens' => 256]
    )
    ->get();

echo "USER: What is capital of France\n";
echo "ASSISTANT: $answer\n";
assert(Str::contains($answer, 'Paris'));
?>
```