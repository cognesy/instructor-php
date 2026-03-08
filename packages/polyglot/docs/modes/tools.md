---
title: Tool Calling
description: 'Use tools and toolChoice explicitly with Polyglot.'
---

Tool calling in Polyglot is configured through `tools` and `toolChoice`.

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$response = Inference::using('openai')
    ->with(
        messages: 'Get the weather in Paris.',
        tools: [[
            'type' => 'function',
            'function' => [
                'name' => 'get_weather',
                'description' => 'Get current weather for a city',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'city' => ['type' => 'string'],
                    ],
                    'required' => ['city'],
                ],
            ],
        ]],
        toolChoice: 'auto',
    )
    ->response();

$toolCalls = $response->toolCalls();
```

If you want tool-call arguments as JSON data, use `asToolCallJsonData()`.
