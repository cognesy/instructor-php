---
title: 'Prompt Templates'
docname: 'prompt_templates'
---

## Overview

`Template` class in Instructor PHP provides a way to define and use
prompt templates using Twig, Blade or custom 'arrowpipe' template syntax.


## Example

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Addons\Prompt\Template;
use Cognesy\LLM\LLM\Inference;
use Cognesy\Utils\Str;

// EXAMPLE 1: Define prompt template inline (don't use files) and use short syntax

$prompt = Template::twig()
    ->from('What is capital of {{country}}')
    ->with(['country' => 'Germany'])
    ->toText();

$answer = (new Inference)->create(
    messages: $prompt
)->toText();

echo "EXAMPLE 1: prompt = $prompt\n";
echo "ASSISTANT: $answer\n";
echo "\n";
assert(Str::contains($answer, 'Berlin'));


// EXAMPLE 2: Load prompt from file

// use default template language, prompt files are in /prompts/twig/<prompt>.twig
$prompt = Template::text(
    pathOrDsn: 'demo-twig:capital',
    variables: ['country' => 'Germany'],
);

$answer = (new Inference)->create(messages: $prompt)->toText();

echo "EXAMPLE 2: prompt = $prompt\n";
echo "ASSISTANT: $answer\n";
echo "\n";
assert(Str::contains($answer, 'Berlin'));

?>
```
