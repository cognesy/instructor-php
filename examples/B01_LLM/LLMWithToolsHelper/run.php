---
title: 'Generating JSON Schema from PHP classes'
docname: 'llm_with_tools_helper'
id: '012f'
tags:
  - 'llm'
  - 'tool-calling'
  - 'schema-helper'
---
## Overview

Polyglot has a built-in support for dynamically constructing tool calling schema using
`JsonSchema` class.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\ToolChoice;
use Cognesy\Polyglot\Inference\Data\ToolDefinitions;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\JsonSchema\JsonSchema;

$schema = JsonSchema::object(
    properties: [
        JsonSchema::string('name', 'City name'),
        JsonSchema::integer('population', 'City population'),
        JsonSchema::integer('founded', 'Founding year'),
    ],
    requiredProperties: ['name', 'population', 'founded'],
);

$data = Inference::using('openai')
    ->with(
        messages: Messages::fromString('What is capital of France? Respond with function call.'),
        tools: ToolDefinitions::fromArray([
            $schema->toFunctionCall(
                functionName: 'provide_data',
                functionDescription: 'Provide city data'
            ),
        ]),
        toolChoice: ToolChoice::specific('provide_data'),
        options: ['max_tokens' => 64],
    )
    ->asToolCallJsonData();

echo "USER: What is capital of France\n";
echo "ASSISTANT:\n";
dump($data);

assert(is_array($data));
assert(is_string($data['name']));
assert(is_int($data['population']));
assert(is_int($data['founded']));
?>
```
