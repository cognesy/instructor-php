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

class Person {
    public string $name;
    public int $age;
    public string $city;
}

echo "=== Example 2: Custom extractor with streaming updates ===\n\n";

$stream = (new StructuredOutput)
    ->withResponseClass(Person::class)
    ->withExtractors(
        new DirectJsonExtractor(),
        new XmlJsonExtractor('json'),
    )
    ->withMessages("Extract person data...")
    ->stream();

foreach ($stream->responses() as $partial) {
    $name = $partial->name ?? '...';
    echo "Partial: {$name}\n";
}

$person = $stream->finalValue();
echo "Final: {$person->name}\n";
