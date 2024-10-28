---
title: 'Prompts'
docname: 'prompt_text'
---

## Overview

`Prompt` class in Instructor PHP provides a way to define and use
prompt templates using Twig or Blade template syntax.


## Example

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Extras\Prompt\Prompt;
use Cognesy\Instructor\Features\LLM\Inference;
use Cognesy\Instructor\Utils\Str;

// EXAMPLE 1: Simplfied API

// use default template language, prompt files are in /prompts/twig/<prompt>.twig
$text = Prompt::text('capital', ['country' => 'Germany']);
$answer = (new Inference)->create(messages: $text)->toText();

echo "EXAMPLE 1: prompt = $text\n";
echo "ASSISTANT: $answer\n";
echo "\n";
assert(Str::contains($answer, 'Berlin'));

// EXAMPLE 2: Define prompt template inline

$text = Prompt::using('twig')
    ->withTemplateContent('What is capital of {{country}}')
    ->withValues(['country' => 'Germany'])
    ->toText();
$answer = (new Inference)->create(messages: $text)->toText();

echo "EXAMPLE 2: prompt = $text\n";
echo "ASSISTANT: $answer\n";
echo "\n";
assert(Str::contains($answer, 'Berlin'));

?>
```
