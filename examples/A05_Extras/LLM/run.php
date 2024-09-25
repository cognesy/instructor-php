---
title: 'Working directly with LLMs'
docname: 'llm'
---

## Overview

LLM class offers access to LLM APIs and convenient methods to execute
model inference, incl. chat completions, tool calling or JSON output
generation.

LLM providers access details can be found and modified via
`/config/llm.php`.


## Example

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Extras\LLM\Inference;
use Cognesy\Instructor\Utils\Str;

$answer = (new Inference)
    ->withConnection('openai') // optional, default is set in /config/llm.php
    ->create(messages: 'What is capital of France?')
    ->toText();

assert(Str::contains($answer, 'Paris'));
echo $answer;
?>
```
