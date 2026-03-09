---
title: Tool Calling
description: Request native tool calls with explicit tool definitions.
---

Tool use is controlled by `tools` and `toolChoice`.

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$response = Inference::using('openai')
    ->with(
        messages: 'Get the weather for Paris.',
        tools: [[
            'type' => 'function',
            'function' => [
                'name' => 'get_weather',
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
```

Read the result from:

- `response()->toolCalls()`
- `asToolCallJson()`
- `asToolCallJsonData()`
