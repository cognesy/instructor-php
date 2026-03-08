---
title: Response Shaping Overview
description: 'Learn how to shape Polyglot inference responses explicitly in 2.0.'
---

In Polyglot 2.0, response shaping is explicit.

Polyglot no longer exposes `OutputMode`. Raw inference requests are built from plain request data:

- `responseFormat` for native text, JSON object, or JSON schema response formats
- `tools` for tool definitions
- `toolChoice` for tool selection policy

## Default behavior

If you do not set `responseFormat`, Polyglot requests plain text output.

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$text = Inference::using('openai')
    ->with(messages: 'Say hello in one sentence.')
    ->get();
```

## Native JSON object

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$data = Inference::using('openai')
    ->with(
        messages: 'Return JSON with name and population for Paris.',
        responseFormat: ['type' => 'json_object'],
    )
    ->asJsonData();
```

## Native JSON schema

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$data = Inference::using('openai')
    ->with(
        messages: 'Return city data as JSON.',
        responseFormat: [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'city_data',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'population' => ['type' => 'integer'],
                    ],
                    'required' => ['name', 'population'],
                ],
                'strict' => true,
            ],
        ],
    )
    ->asJsonData();
```

## Tool calling

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$response = Inference::using('openai')
    ->with(
        messages: 'Look up the weather in Paris.',
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
```

## Markdown JSON fallback

Markdown JSON fallback is not a Polyglot concept in 2.0.

If you need that behavior, use Instructor and `Cognesy\Instructor\Enums\OutputMode::MdJson`.
