---
title: JSON Schema Responses
description: Request native schema-constrained JSON when the provider supports it.
---

JSON Schema mode takes structured output a step further by validating the response against a predefined schema. When the provider supports it natively, the schema is enforced at the API level, guaranteeing that the response matches the exact structure you defined.

## Basic Usage

Use `ResponseFormat::jsonSchema()` to create a response format with a schema definition. Polyglot forwards the schema directly to the provider:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Inference;

$data = Inference::using('openai')
    ->with(
        messages: Messages::fromString('Return a city record as JSON.'),
        responseFormat: ResponseFormat::jsonSchema(
            schema: [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'country' => ['type' => 'string'],
                ],
                'required' => ['name', 'country'],
            ],
            name: 'city_record',
            strict: true,
        ),
    )
    ->asJsonData();

// $data is guaranteed to have 'name' and 'country' keys
echo "{$data['name']}, {$data['country']}\n";
```

## Using the Fluent API

You can also set the response format with the `withResponseFormat()` method, using the `ResponseFormat::jsonSchema()` factory:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Inference;

$schema = [
    'type' => 'object',
    'properties' => [
        'title' => ['type' => 'string'],
        'author' => ['type' => 'string'],
        'year' => ['type' => 'integer'],
    ],
    'required' => ['title', 'author', 'year'],
];

$data = Inference::using('openai')
    ->withMessages(Messages::fromString('Return a book record for "1984" by George Orwell.'))
    ->withResponseFormat(ResponseFormat::jsonSchema(
        schema: $schema,
        name: 'book_record',
        strict: true,
    ))
    ->asJsonData();
```

## Complex Nested Schemas

JSON Schema mode shines when you need complex, nested data structures. The provider will enforce every level of the schema:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Inference;

$schema = [
    'type' => 'object',
    'properties' => [
        'location' => [
            'type' => 'string',
            'description' => 'The city and country',
        ],
        'current_temperature' => [
            'type' => 'number',
            'description' => 'Current temperature in Celsius',
        ],
        'conditions' => [
            'type' => 'string',
            'description' => 'Current weather conditions',
        ],
        'forecast' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'day' => ['type' => 'string'],
                    'high' => ['type' => 'number'],
                    'low' => ['type' => 'number'],
                    'conditions' => ['type' => 'string'],
                ],
                'required' => ['day', 'high', 'low', 'conditions'],
            ],
        ],
    ],
    'required' => ['location', 'current_temperature', 'conditions', 'forecast'],
];

$data = Inference::using('openai')
    ->with(
        messages: Messages::fromString('Provide a weather report for Paris, France.'),
        responseFormat: ResponseFormat::jsonSchema(
            schema: $schema,
            name: 'weather_report',
            strict: true,
        ),
    )
    ->asJsonData();

echo "Weather in {$data['location']}: {$data['conditions']}, {$data['current_temperature']}C\n";
foreach ($data['forecast'] as $day) {
    echo "  {$day['day']}: {$day['low']}C - {$day['high']}C, {$day['conditions']}\n";
}
```

## How Schema Validation Works

With JSON Schema mode, the validation pipeline depends on the provider:

1. The schema is sent to the provider as part of the API request.
2. The model structures its response to match the schema.
3. For providers with native support (like OpenAI), validation happens at the API level before the response is returned.
4. Polyglot forwards the native schema request. It does not emulate schema enforcement for providers that lack it.

When `strict` is set to `true`, the provider will reject any response that does not conform to the schema and retry internally. This gives you strong guarantees about the output structure.

## Provider Support

Provider support for JSON Schema varies significantly:

| Provider | JSON Schema Support |
|---|---|
| OpenAI (GPT-4 and newer) | Full native support with strict mode |
| Groq, Fireworks, and others | Varies -- check `DriverCapabilities` |
| Anthropic | Not supported natively |

You can query support programmatically:

```php
// DriverCapabilities::supportsResponseFormatJsonSchema()
```

For providers without native JSON Schema support, consider using [JSON object mode](/modes/json) with detailed prompts, or use the Instructor layer above Polyglot for automatic fallback strategies.

## When to Use JSON Schema Mode

JSON Schema mode is ideal for:

- Applications requiring strictly typed data with guaranteed structure
- Integration with databases or APIs that expect specific field names and types
- Data extraction with complex, nested structures
- Ensuring consistent response formats across many requests
- Any situation where a malformed response would cause downstream failures
