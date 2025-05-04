---
title: JSON Mode
description: 'Learn how to use JSON mode in Polyglot for structured LLM responses.'
---

JSON mode instructs the model to return responses formatted as valid JSON objects. This is useful when you need structured data that can be easily processed by your application.

### Basic JSON Generation

```php
<?php
use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

$inference = new Inference();

$response = $inference->create(
    messages: 'List the top 3 most populous cities in the world with their populations.',
    mode: OutputMode::Json
)->toJson();

// $response is now a PHP array parsed from the JSON
echo "Top cities:\n";
foreach ($response['cities'] as $city) {
    echo "- {$city['name']}: {$city['population']} million\n";
}
```

### Structuring JSON Responses with Instructions

For best results, include clear instructions about the expected JSON structure:

```php
<?php
use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

$inference = new Inference();

// Include the expected structure in the prompt
$prompt = <<<EOT
List the top 3 most populous cities in the world.
Return your answer as a JSON object with the following structure:
{
  "cities": [
    {
      "name": "City name",
      "country": "Country name",
      "population": population in millions (number)
    },
    ...
  ]
}
EOT;

$response = $inference->create(
    messages: $prompt,
    mode: OutputMode::Json
)->toJson();

// Process the response
echo "Top cities by population:\n";
foreach ($response['cities'] as $index => $city) {
    echo ($index + 1) . ". {$city['name']}, {$city['country']}: {$city['population']} million\n";
}
```

### Provider-Specific JSON Options

Some providers offer additional options for JSON mode:

```php
<?php
use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

// OpenAI example
$inference = new Inference()->withConnection('openai');

$response = $inference->create(
    messages: 'List the top 3 most populous cities in the world.',
    mode: OutputMode::Json,
    options: [
        'response_format' => ['type' => 'json_object'],
        // Other OpenAI-specific options...
    ]
)->toJson();

// The response will be a JSON object
```

### When to Use JSON Mode

JSON mode is ideal for:
- Extracting structured data (lists, records, etc.)
- API responses that need to be machine-readable
- Generating data for web applications
- Creating datasets
