---
title: 'Custom Extraction Strategies'
docname: 'custom_extractor'
---

## Overview

Instructor uses a pluggable extraction strategy system to parse JSON from LLM responses.
Different LLMs and output modes may return JSON in various formats - wrapped in markdown,
embedded in explanatory text, or with trailing commas.

You can create custom extraction strategies to handle specific response formats from
your LLM or API. Strategies are tried in order until one succeeds.

## Built-in Strategies

Instructor provides these extraction strategies:

- `DirectJsonStrategy` - Parses content directly as JSON (fastest)
- `BracketMatchingStrategy` - Finds JSON by matching first `{` to last `}`
- `MarkdownJsonStrategy` - Extracts from markdown code blocks
- `ResilientJsonStrategy` - Handles trailing commas, missing braces

## Example: Custom XML Wrapper Strategy

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\Extraction\Contracts\ExtractionStrategy;
use Cognesy\Instructor\Extraction\Strategies\DirectJsonStrategy;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Utils\Result\Result;

/**
 * Custom strategy that extracts JSON from XML-like wrappers.
 *
 * Some LLMs or custom APIs return JSON wrapped in XML tags like:
 * <response><json>{"name":"John"}</json></response>
 */
class XmlJsonExtractor implements ExtractionStrategy
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

echo "=== Demonstrating Custom Extraction Strategy ===\n\n";
echo "Raw LLM response:\n";
echo str_repeat('-', 50) . "\n";
echo $xmlWrappedResponse . "\n";
echo str_repeat('-', 50) . "\n\n";

// Use custom extraction strategies
// DirectJson is tried first (will fail), then XmlJsonExtractor (will succeed)
$person = (new StructuredOutput)
    ->withResponseClass(Person::class)
    ->withExtractionStrategies(
        new DirectJsonStrategy(),      // Try direct parsing first
        new XmlJsonExtractor('json'),  // Fall back to XML wrapper extraction
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

## Streaming with Custom Strategies

For streaming responses, use `withStreamingExtractionStrategies()` to apply
custom extraction during streaming (not just on the final response):

```php
<?php
// Custom strategies for streaming
$stream = (new StructuredOutput)
    ->withResponseClass(Person::class)
    ->withStreamingExtractionStrategies(
        new DirectJsonStrategy(),
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

## Creating Your Own Strategy

Implement `ExtractionStrategy` interface:

```php
use Cognesy\Instructor\Extraction\Contracts\ExtractionStrategy;
use Cognesy\Utils\Result\Result;

class MyCustomStrategy implements ExtractionStrategy
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

## Strategy Chain Behavior

Strategies are tried in order until one succeeds:

1. First strategy is called with raw content
2. If it returns `Result::success()`, extraction is complete
3. If it returns `Result::failure()`, next strategy is tried
4. If all fail, an error is raised

This allows graceful degradation - try fast/simple strategies first,
fall back to more complex ones only when needed.
