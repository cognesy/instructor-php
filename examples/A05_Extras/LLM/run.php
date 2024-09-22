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

$answerGen = (new Inference)
    ->withConnection('together')
    ->create(messages: 'What is capital of France?', options: ['stream' => true])
    ->asStream();

foreach ($answerGen as $answer) {
    echo $answer;
}
?>
```
