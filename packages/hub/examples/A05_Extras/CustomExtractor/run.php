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
