---
title: JSON Schema Mode
description: 'Learn how to use JSON Schema mode in Polyglot for structured and validated LLM responses.'
---


JSON Schema mode takes JSON generation a step further by validating the response against a predefined schema. This guarantees the response has the expected structure when the provider supports native JSON Schema validation.

## Defining and Using a JSON Schema

```php
<?php
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

$inference = Inference::using('openai');  // Currently best supported by OpenAI

// Define a schema for a weather report
$schema = [
    'type' => 'object',
    'properties' => [
        'location' => [
            'type' => 'string',
            'description' => 'The city and country'
        ],
        'current_temperature' => [
            'type' => 'number',
            'description' => 'Current temperature in Celsius'
        ],
        'conditions' => [
            'type' => 'string',
            'description' => 'Current weather conditions (e.g., sunny, rainy)'
        ],
        'forecast' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'day' => [
                        'type' => 'string',
                        'description' => 'Day of the week'
                    ],
                    'temperature_high' => [
                        'type' => 'number',
                        'description' => 'Expected high temperature in Celsius'
                    ],
                    'temperature_low' => [
                        'type' => 'number',
                        'description' => 'Expected low temperature in Celsius'
                    ],
                    'conditions' => [
                        'type' => 'string',
                        'description' => 'Expected weather conditions'
                    ]
                ],
                'required' => ['day', 'temperature_high', 'temperature_low', 'conditions']
            ],
            'description' => 'Three-day weather forecast'
        ]
    ],
    'required' => ['location', 'current_temperature', 'conditions', 'forecast']
];

// Request a weather report
$response = $inference->with(
    messages: 'Provide a weather report for Paris, France.',
    responseFormat: [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'weather_report',
            'schema' => $schema,
            'strict' => true,
        ],
    ],
    mode: OutputMode::JsonSchema
)->asJsonData();

// The response will match the schema's structure exactly
echo "Weather in {$response['location']}:\n";
echo "Currently {$response['conditions']} and {$response['current_temperature']}°C\n\n";

echo "Forecast:\n";
foreach ($response['forecast'] as $day) {
    echo "{$day['day']}: {$day['conditions']}, {$day['temperature_low']}°C to {$day['temperature_high']}°C\n";
}
```


## Schema Validation

With JSON Schema mode, Polyglot enforces the schema when native support is available:

1. The schema is sent to the model as part of the request
2. The model structures its response to match the schema
3. For providers with native schema support, validation happens at the API level
4. For other providers, results are best-effort and should be treated as JSON output without strict guarantees


## Provider Support for JSON Schema

Provider support for JSON Schema varies:

- **OpenAI (GPT-4 and newer)**: Native support with `json_schema` response format
- **Other providers**: No native guarantee; use `OutputMode::Json` or `OutputMode::MdJson` for best-effort output

For guaranteed schema validation, use a provider with native JSON Schema support.


## When to Use JSON Schema Mode

JSON Schema mode is ideal for:
- Applications requiring strictly typed data
- Integration with databases or APIs that expect specific structures
- Data extraction with complex nested structures
- Ensuring consistent response formats across multiple requests
