---
title: Structured outputs with JsonSchema class
description: Learn how to use JSON Schemas to shape Polyglot inference requests.
---

`JsonSchema` helps you build the schema payload for native JSON schema response formats.

## Quick start

```php
<?php
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\JsonSchema\JsonSchema;

$schema = JsonSchema::object(
    properties: [
        JsonSchema::string('name', description: 'City name'),
        JsonSchema::integer('population', description: 'City population'),
        JsonSchema::integer('founded', description: 'Founding year'),
    ],
    requiredProperties: ['name', 'population', 'founded'],
);

$data = Inference::using('openai')
    ->with(
        messages: [
            ['role' => 'user', 'content' => 'What is the capital of France? Respond with JSON data.'],
        ],
        responseFormat: [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'city_data',
                'schema' => $schema->toJsonSchema(),
                'strict' => true,
            ],
        ],
        options: ['max_tokens' => 64],
    )
    ->asJsonData();
```

## Provider boundary

Polyglot only models native provider request parameters.

- Use `responseFormat` for native JSON object or JSON schema response shaping.
- Use `tools` and `toolChoice` for tool calling.
- Use Instructor if you need prompt-based fallback strategies such as markdown-wrapped JSON.
