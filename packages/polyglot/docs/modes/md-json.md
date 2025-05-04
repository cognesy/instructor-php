---
title: MdJSON Mode
description: 'Learn how to use Markdown JSON mode in Polyglot for structured LLM responses.'
---


Markdown JSON mode is a special mode that requests the model to format its response as JSON within a Markdown code block. This is particularly useful for models or providers that don't have native JSON output support.

### Using Markdown JSON Mode

```php
<?php
use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

$inference = new Inference();

// This works with virtually any provider
$response = $inference->create(
    messages: 'List three programming languages and their key features.',
    mode: OutputMode::MdJson
)->toJson();

// The model will return JSON wrapped in Markdown, which Polyglot processes for you
foreach ($response['languages'] as $language) {
    echo "{$language['name']} - {$language['paradigm']}\n";
    echo "Key features: " . implode(', ', $language['key_features']) . "\n\n";
}
```

### How MdJson Mode Works

1. Polyglot instructs the model to respond with a JSON object wrapped in a Markdown code block
2. The model formats its response accordingly (```json {...} ```)
3. Polyglot extracts the JSON content from the Markdown code block
4. The JSON is parsed and returned to your application

### Providing Guidance for MdJson

While MdJson is more flexible across providers, you still need to provide clear instructions:

```php
<?php
use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

$inference = new Inference();

// Include expected format in the prompt
$prompt = <<<EOT
List three programming languages with their key features.
Respond with a JSON object following this structure:
```json
{
  "languages": [
    {
      "name": "Language name",
      "paradigm": "Programming paradigm",
      "year_created": year as number,
      "key_features": ["feature1", "feature2", "feature3"]
    },
    ...
  ]
}
```
EOT;

$response = $inference->create(
messages: $prompt,
mode: OutputMode::MdJson
)->toJson();

// Process as normal JSON
```

### When to Use MdJson Mode

MdJson mode is ideal for:
- Working with providers that don't have native JSON output
- Ensuring portability across different providers
- Getting structured responses from older model versions
- Fallback option when JSON Schema mode isn't available
