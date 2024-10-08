---
title: 'Working directly with LLMs and JSON - MdJSON mode'
docname: 'llm_md_json'
---

## Overview

While working with `Inference` class, you can also generate JSON output
from the model inference. This is useful for example when you need to
process the response in a structured way or when you want to store the
elements of the response in a database.

## Example

In this example we will use emulation mode - MdJson, which tries to
force the model to generate a JSON output by asking it to respond
with a JSON object within a Markdown code block.

This is useful for the models which do not support JSON output directly.

We will also provide an example of the expected JSON output in the prompt
to guide the model in generating the correct response.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Features\LLM\Inference;

$data = (new Inference)
    ->withConnection('openai')
    ->create(
        messages: [['role' => 'user', 'content' => 'What is capital of France? \
           Respond with JSON data containing name", population and year of founding. \
           Example: {"name": "Berlin", "population": 3700000, "founded": 1237}']],
        options: ['max_tokens' => 64],
        mode: Mode::MdJson,
    )
    ->toJson();

echo "USER: What is capital of France\n";
echo "ASSISTANT:\n";
dump($data);

?>
```
