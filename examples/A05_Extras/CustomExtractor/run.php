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
