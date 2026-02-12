---
title: 'Working directly with LLMs and JSON - JSON mode'
docname: 'llm_json'
id: 'efd0'
---
## Overview

While working with `Inference` class, you can also generate JSON output
from the model inference. This is useful for example when you need to
process the response in a structured way or when you want to store the
elements of the response in a database.

`Inference` class supports multiple inference modes, like `Tools`, `Json`
`JsonSchema` or `MdJson`, which gives you flexibility to choose the best
approach for your use case.

## Example

In this example we will use OpenAI JSON mode, which guarantees that the
response will be in a JSON format.

It does not guarantee compliance with a specific schema (for some providers
including OpenAI). We can try to work around it by providing an example of
the expected JSON output in the prompt.

> NOTE: Some model providers allow to specify a JSON schema for model to
follow via `schema` parameter of `response_format`. OpenAI does not support
this feature in JSON mode (only in JSON Schema mode).

```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;

$data = (new Inference)
    ->using('openai') // optional, default is set in /config/llm.php
    ->with(
        messages: [['role' => 'user', 'content' => 'What is capital of France? \
           Respond with JSON data containing name", population and year of founding. \
           Example: {"name": "Berlin", "population": 3700000, "founded": 1237}']],
        responseFormat: [
            'type' => 'json_object',
        ],
        options: ['max_tokens' => 64],
        mode: OutputMode::Json,
    )
    ->asJsonData();

echo "USER: What is capital of France\n";
echo "ASSISTANT:\n";
dump($data);

assert(is_array($data), 'Response should be an array');
assert(isset($data['name']), 'Response should have "name" field');
assert(strpos($data['name'], 'Paris') !== false, 'City name should be Paris');
assert(isset($data['population']), 'Response should have "population" field');
assert(isset($data['founded']), 'Response should have "founded" field');
?>
```
