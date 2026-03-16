---
title: 'Working directly with LLMs and JSON - Tool calling'
docname: 'llm_tools'
id: '3778'
---
## Overview

While working with `Inference` class, you can also generate JSON output
from the model inference. This is useful for example when you need to
process the response in a structured way or when you want to store the
elements of the response in a database.

## Example

In this example we will use OpenAI tool calling, in which model will generate
a JSON containing arguments for a function call. This way we can make the
model generate a JSON object with specific structure of parameters.

```php
<?php
require 'examples/boot.php';

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\ToolChoice;
use Cognesy\Polyglot\Inference\Data\ToolDefinitions;
use Cognesy\Polyglot\Inference\Inference;

$response = Inference::using('openai')
    ->with(
        messages: Messages::fromString('What is capital of France? Respond with function call.'),
        tools: ToolDefinitions::fromArray([[
            'type' => 'function',
            'function' => [
                'name' => 'extract_data',
                'description' => 'Extract city data',
                'parameters' => [
                    'type' => 'object',
                    'description' => 'City information',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'City name',
                        ],
                        'founded' => [
                            'type' => 'integer',
                            'description' => 'Founding year',
                        ],
                        'population' => [
                            'type' => 'integer',
                            'description' => 'Current population',
                        ],
                    ],
                    'required' => ['name', 'founded', 'population'],
                    'additionalProperties' => false,
                ],
            ],
        ]]),
        toolChoice: ToolChoice::specific('extract_data'),
        options: ['max_tokens' => 64],
    )
    ->response();

$data = $response->toolCalls()->first()?->args() ?? [];

echo "USER: What is capital of France\n";
echo "ASSISTANT:\n";
dump($data);

assert(is_array($data), 'Response should be an array');
assert(isset($data['name']), 'Response should have "name" field');
assert(is_string($data['name']) && $data['name'] !== '', 'City name should be a non-empty string');
assert(array_key_exists('population', $data), 'Response should have "population" field');
assert(array_key_exists('founded', $data), 'Response should have "founded" field');
?>
```
