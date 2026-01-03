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

use Cognesy\Instructor\Extraction\Contracts\CanExtractContent;
use Cognesy\Instructor\Extraction\Extractors\DirectJsonExtractor;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Utils\Result\Result;

/**
 * Custom extractor that extracts JSON from XML-like wrappers.
 *
 * Some LLMs or custom APIs return JSON wrapped in XML tags like:
 * <response><json>{"name":"John"}</json></response>
 */
class XmlJsonExtractor implements CanExtractContent
{
    public function __construct(
        private string $tagName = 'json',
    ) {}

    #[\Override]
    public function extract(string $content): Result
    {
        // Match: <json>{"key": "value"}</json>
        $pattern = sprintf('/<%s>(.*?)<\/%s>/s', $this->tagName, $this->tagName);

        if (preg_match($pattern, $content, $matches)) {
            $json = trim($matches[1]);

            try {
                json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
                return Result::success($json);
            } catch (\JsonException $e) {
                return Result::failure("Invalid JSON in <{$this->tagName}>: {$e->getMessage()}");
            }
        }

        return Result::failure("No <{$this->tagName}> wrapper found");
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

echo "=== Demonstrating Custom Content Extractor ===\n\n";
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

Implement `CanExtractContent` interface:

```php
use Cognesy\Instructor\Extraction\Contracts\CanExtractContent;
use Cognesy\Utils\Result\Result;

class MyCustomExtractor implements CanExtractContent
{
    public function extract(string $content): Result
    {
        // Your extraction logic here
        // Return Result::success($json) on success
        // Return Result::failure($reason) on failure
    }

    public function name(): string
    {
        return 'my_custom';  // For logging/debugging
    }
}
```

## Extractor Chain Behavior

Extractors are tried in order until one succeeds:

1. First extractor is called with raw content
2. If it returns `Result::success()`, extraction is complete
3. If it returns `Result::failure()`, next extractor is tried
4. If all fail, an error is raised

This allows graceful degradation - try fast/simple extractors first,
fall back to more complex ones only when needed.
