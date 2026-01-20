---
title: 'Custom Content Extractors'
docname: 'custom_extractor'
---

## Overview

Instructor uses a pluggable extraction system to parse structured content from LLM responses.
Different LLMs and output modes may return content in various formats - wrapped in markdown,
embedded in explanatory text, or with trailing commas.

You can create custom extractors to handle specific response formats from
your LLM or API. Extractors are tried in order until one succeeds.

## Built-in Extractors

Instructor provides these content extractors:

- `DirectJsonExtractor` - Parses content directly as JSON (fastest)
- `BracketMatchingExtractor` - Finds JSON by matching first `{` to last `}`
- `MarkdownBlockExtractor` - Extracts from markdown code blocks
- `ResilientJsonExtractor` - Handles trailing commas, missing braces
- `SmartBraceExtractor` - Smart brace matching with string escaping

## Example: Custom XML Wrapper Extractor

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\Extraction\Exceptions\ExtractionException;
use Cognesy\Instructor\Extraction\Extractors\DirectJsonExtractor;
use Cognesy\Instructor\StructuredOutput;

/**
 * Custom extractor that extracts JSON from XML-like wrappers.
 *
 * Some LLMs or custom APIs return JSON wrapped in XML tags like:
 * <response><json>{"name":"John"}</json></response>
 */
class XmlJsonExtractor implements CanExtractResponse
{
    public function __construct(
        private string $tagName = 'json',
    ) {}

    #[\Override]
    public function extract(ExtractionInput $input): array
    {
        // Match: <json>{"key": "value"}</json>
        $pattern = sprintf('/<%s>(.*?)<\/%s>/s', $this->tagName, $this->tagName);

        if (!preg_match($pattern, $input->content, $matches)) {
            throw new ExtractionException("No <{$this->tagName}> wrapper found");
        }

        $json = trim($matches[1]);
        if ($json === '') {
            throw new ExtractionException("Empty <{$this->tagName}> wrapper");
        }

        try {
            $decoded = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ExtractionException("Invalid JSON in <{$this->tagName}>: {$e->getMessage()}", $e);
        }

        if (!is_array($decoded)) {
            throw new ExtractionException("Expected object or array in <{$this->tagName}>");
        }

        return $decoded;
    }

    #[\Override]
    public function name(): string
    {
        return 'xml_json_extractor';
    }
}

// Define schema
class Person {
    public string $name;
    public int $age;
    public string $city;
}

// Simulate an LLM response with XML-wrapped JSON
$xmlWrappedResponse = <<<EOT
Here is the extracted information:

<json>
{
    "name": "Alice Johnson",
    "age": 28,
    "city": "San Francisco"
}
</json>

The data has been successfully extracted from the input.
EOT;

echo "=== Example 1: Custom extractor for XML-wrapped JSON (sync) ===\n\n";
echo "Raw LLM response:\n";
echo str_repeat('-', 50) . "\n";
echo $xmlWrappedResponse . "\n";
echo str_repeat('-', 50) . "\n\n";

// Use custom extractors
// DirectJson is tried first (will fail), then XmlJsonExtractor (will succeed)
$person = (new StructuredOutput)
    ->withResponseClass(Person::class)
    ->withExtractors(
        new DirectJsonExtractor(),      // Try direct parsing first
        new XmlJsonExtractor('json'),   // Fall back to XML wrapper extraction
    )
    ->withMessages("Extract: Alice Johnson, 28 years old, lives in San Francisco")
    ->get();

dump($person);

echo "\nExtracted data:\n";
echo "Name: {$person->name}\n";
echo "Age: {$person->age}\n";
echo "City: {$person->city}\n";
?>
```

## Expected Output

```
=== Demonstrating Custom Extraction Strategy ===

Raw LLM response:
--------------------------------------------------
Here is the extracted information:

<json>
{
    "name": "Alice Johnson",
    "age": 28,
    "city": "San Francisco"
}
</json>

The data has been successfully extracted from the input.
--------------------------------------------------

Person {
  +name: "Alice Johnson"
  +age: 28
  +city: "San Francisco"
}

Extracted data:
Name: Alice Johnson
Age: 28
City: San Francisco
```

## Streaming with Custom Extractors

Custom extractors are automatically used for both sync and streaming modes.
The `ResponseExtractor` handles buffer creation internally, using a subset
of extractors optimized for streaming (fast extractors by default).

```php
<?php
// Custom extractors work for streaming too
$stream = (new StructuredOutput)
    ->withResponseClass(Person::class)
    ->withExtractors(
        new DirectJsonExtractor(),
        new XmlJsonExtractor('json'),
    )
    ->withMessages("Extract person data...")
    ->stream();

foreach ($stream->responses() as $partial) {
    echo "Partial: " . ($partial->name ?? '...') . "\n";
}

$person = $stream->finalValue();
echo "Final: {$person->name}\n";
?>
```

## Creating Your Own Extractor

Implement `CanExtractResponse` interface:

```php
use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\Extraction\Exceptions\ExtractionException;

class MyCustomExtractor implements CanExtractResponse
{
    public function extract(ExtractionInput $input): array
    {
        // Your extraction logic here
        // Return decoded array on success
        // Throw ExtractionException on failure
    }

    public function name(): string
    {
        return 'my_custom';  // For logging/debugging
    }
}
```

## Extractor Chain Behavior

Extractors are tried in order until one succeeds:

1. First extractor is called with ExtractionInput
2. If it returns an array, extraction is complete
3. If it throws ExtractionException, next extractor is tried
4. If all fail, an error is raised

This allows graceful degradation - try fast/simple extractors first,
fall back to more complex ones only when needed.
