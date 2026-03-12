---
title: JSON Object Responses
description: Ask the provider for native JSON object output using the response format field.
---

JSON object mode instructs the model to return its response as a valid JSON object. This is useful when you need structured data that can be easily processed by your application, without defining a full schema.

## Basic Usage

Use `ResponseFormat::jsonObject()` to request JSON output. The `asJsonData()` convenience method decodes the response into a PHP array:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Inference;

$data = Inference::using('openai')
    ->withMessages(Messages::fromString('Return JSON with keys "name" and "role".'))
    ->withResponseFormat(ResponseFormat::jsonObject())
    ->asJsonData();

// $data is now a PHP array, e.g. ['name' => 'Alice', 'role' => 'Engineer']
```

The `asJsonData()` method only decodes the returned content. Validation and structure depend on the provider and your prompt -- there are no schema guarantees with this mode.

## Guiding the JSON Structure

For best results, include clear instructions about the expected JSON structure directly in your prompt. The model will follow your guidance, but without a schema there is no enforcement:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Inference;

$prompt = <<<EOT
List the top 3 most populous cities in the world.
Return your answer as a JSON object with the following structure:
{
  "cities": [
    {
      "name": "City name",
      "country": "Country name",
      "population": population in millions (number)
    }
  ]
}
EOT;

$data = Inference::using('openai')
    ->with(
        messages: Messages::fromString($prompt),
        responseFormat: ResponseFormat::jsonObject(),
    )
    ->asJsonData();

foreach ($data['cities'] as $city) {
    echo "{$city['name']}, {$city['country']}: {$city['population']} million\n";
}
```

## Using the Fluent API

You can also set the response format with the dedicated `withResponseFormat()` method:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Inference;

$data = Inference::using('openai')
    ->withMessages(Messages::fromString('List three programming languages as JSON with name and year fields.'))
    ->withResponseFormat(ResponseFormat::jsonObject())
    ->asJsonData();
```

## Getting JSON as a String

If you need the raw JSON string instead of a decoded array, use `asJson()`:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Inference;

$json = Inference::using('openai')
    ->withMessages(Messages::fromString('Return a JSON object with a greeting.'))
    ->withResponseFormat(ResponseFormat::jsonObject())
    ->asJson();

// $json is a string like '{"greeting": "Hello, world!"}'
```

## Provider Support

Most major providers support native JSON object mode, including OpenAI, Groq, Fireworks, and others. Some providers (such as Anthropic) do not support `responseFormat` natively -- for those, consider using tool calls to extract structured data, or use the Instructor layer above Polyglot for prompt-based fallback strategies.

You can query a driver's capabilities programmatically through `DriverCapabilities::supportsResponseFormatJsonObject()`.

## When to Use JSON Object Mode

JSON object mode is ideal for:

- Extracting structured data (lists, records, key-value pairs)
- API responses that need to be machine-readable
- Generating datasets or feeding data into downstream processing
- Cases where you want structured output but do not need strict schema validation

If you need guaranteed field names, types, and required properties, consider [JSON Schema mode](/modes/json-schema) instead.
