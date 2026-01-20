<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Feature\Instructor;

use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\Extraction\Exceptions\ExtractionException;
use Cognesy\Instructor\Extraction\Extractors\DirectJsonExtractor;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\MockHttp;

class CustomExtractorUser
{
    public function __construct(
        public string $name = '',
        public int $age = 0,
    ) {}
}

// Custom extractor that always returns fixed data
class FixedDataExtractor implements CanExtractResponse
{
    public function __construct(private array $data) {}

    public function extract(ExtractionInput $input): array
    {
        return $this->data;
    }

    public function name(): string
    {
        return 'fixed_data';
    }
}

// Custom extractor that extracts from XML-like format
class XmlJsonExtractor implements CanExtractResponse
{
    public function extract(ExtractionInput $input): array
    {
        // Simple pattern: <json>{"data":"here"}</json>
        if (preg_match('/<json>(.*?)<\/json>/s', $input->content, $matches)) {
            $json = trim($matches[1]);
            $decoded = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new ExtractionException('XML-wrapped JSON must decode to object or array');
            }
            return $decoded;
        }

        throw new ExtractionException('No XML-wrapped JSON found');
    }

    public function name(): string
    {
        return 'xml_json';
    }
}

$mockHttp = MockHttp::get([
    '{"name":"John","age":30}'
]);

it('uses custom extractor when provided with withExtractor()', function () use ($mockHttp) {
    $customExtractor = new FixedDataExtractor(['name' => 'Custom', 'age' => 99]);

    $result = (new StructuredOutput())
        ->withHttpClient($mockHttp)
        ->withExtractor($customExtractor)
        ->withResponseClass(CustomExtractorUser::class)
        ->intoArray()
        ->with(messages: 'Get user info')
        ->get();

    expect($result)->toBeArray();
    expect($result['name'])->toBe('Custom');
    expect($result['age'])->toBe(99);
});

it('uses custom extractors with withExtractors()', function () use ($mockHttp) {
    // Only use DirectJsonExtractor - should work for clean JSON
    $result = (new StructuredOutput())
        ->withHttpClient($mockHttp)
        ->withExtractors(DirectJsonExtractor::class)
        ->withResponseClass(CustomExtractorUser::class)
        ->intoArray()
        ->with(messages: 'Get user info')
        ->get();

    expect($result)->toBeArray();
    expect($result['name'])->toBe('John');
    expect($result['age'])->toBe(30);
});

it('custom extractor works for special formats', function () {
    $xmlMock = MockHttp::get(['<json>{"name":"Jane","age":25}</json>']);

    $result = (new StructuredOutput())
        ->withHttpClient($xmlMock)
        ->withExtractors(
            new XmlJsonExtractor(),
            new DirectJsonExtractor(),
        )
        ->withResponseClass(CustomExtractorUser::class)
        ->intoArray()
        ->with(messages: 'Get user info')
        ->get();

    expect($result)->toBeArray();
    expect($result['name'])->toBe('Jane');
    expect($result['age'])->toBe(25);
});

it('custom extractor works with object deserialization', function () use ($mockHttp) {
    $customExtractor = new FixedDataExtractor(['name' => 'Custom', 'age' => 42]);

    $result = (new StructuredOutput())
        ->withHttpClient($mockHttp)
        ->withExtractor($customExtractor)
        ->with(
            messages: 'Get user info',
            responseModel: CustomExtractorUser::class,
        )
        ->get();

    expect($result)->toBeInstanceOf(CustomExtractorUser::class);
    expect($result->name)->toBe('Custom');
    expect($result->age)->toBe(42);
});

it('withExtractor() overrides default extractor', function () use ($mockHttp) {
    // Even though response is {"name":"John","age":30}, custom extractor returns different data
    $customExtractor = new FixedDataExtractor(['name' => 'Override', 'age' => 1]);

    $result = (new StructuredOutput())
        ->withHttpClient($mockHttp)
        ->withExtractor($customExtractor)
        ->withResponseClass(CustomExtractorUser::class)
        ->intoArray()
        ->with(messages: 'Get user info')
        ->get();

    expect($result['name'])->toBe('Override');
    expect($result['age'])->toBe(1);
});

it('withExtractors() creates ResponseExtractor with custom extractors', function () {
    $so = new StructuredOutput();
    $so->withExtractors(DirectJsonExtractor::class);

    // Access via reflection to verify
    $reflection = new \ReflectionClass($so);
    $prop = $reflection->getProperty('extractors');
    
    $extractors = $prop->getValue($so);

    expect($extractors)->toHaveCount(1);
    expect($extractors[0])->toBe(DirectJsonExtractor::class);
});
