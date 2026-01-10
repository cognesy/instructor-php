<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Feature\Instructor;

use Cognesy\Instructor\Extraction\Contracts\CanExtractContent;
use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\Extractors\DirectJsonExtractor;
use Cognesy\Instructor\Extraction\ResponseExtractor;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\MockHttp;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Result\Result;

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

    public function extract(InferenceResponse $response, OutputMode $mode): Result
    {
        return Result::success($this->data);
    }
}

// Custom extractor that extracts from XML-like format
class XmlJsonExtractor implements CanExtractContent
{
    public function extract(string $content): Result
    {
        // Simple pattern: <json>{"data":"here"}</json>
        if (preg_match('/<json>(.*?)<\/json>/s', $content, $matches)) {
            return Result::success($matches[1]);
        }
        return Result::failure('No XML-wrapped JSON found');
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
