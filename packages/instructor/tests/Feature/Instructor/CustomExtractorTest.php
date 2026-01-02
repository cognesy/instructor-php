<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Feature\Instructor;

use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\Contracts\ExtractionStrategy;
use Cognesy\Instructor\Extraction\JsonResponseExtractor;
use Cognesy\Instructor\Extraction\Strategies\DirectJsonStrategy;
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

// Custom strategy that extracts from XML-like format
class XmlJsonStrategy implements ExtractionStrategy
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

it('uses custom extraction strategies with withExtractionStrategies()', function () use ($mockHttp) {
    // Only use DirectJsonStrategy - should work for clean JSON
    $result = (new StructuredOutput())
        ->withHttpClient($mockHttp)
        ->withExtractionStrategies(new DirectJsonStrategy())
        ->withResponseClass(CustomExtractorUser::class)
        ->intoArray()
        ->with(messages: 'Get user info')
        ->get();

    expect($result)->toBeArray();
    expect($result['name'])->toBe('John');
    expect($result['age'])->toBe(30);
});

it('custom strategy works for special formats', function () {
    $xmlMock = MockHttp::get(['<json>{"name":"Jane","age":25}</json>']);

    $result = (new StructuredOutput())
        ->withHttpClient($xmlMock)
        ->withExtractionStrategies(
            new XmlJsonStrategy(),
            new DirectJsonStrategy(),
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

it('withExtractionStrategies() creates JsonResponseExtractor with custom strategies', function () {
    $so = new StructuredOutput();
    $so->withExtractionStrategies(new DirectJsonStrategy());

    // Access via reflection to verify
    $reflection = new \ReflectionClass($so);
    $prop = $reflection->getProperty('extractor');
    $prop->setAccessible(true);
    $extractor = $prop->getValue($so);

    expect($extractor)->toBeInstanceOf(JsonResponseExtractor::class);
    expect($extractor->strategies())->toHaveCount(1);
    expect($extractor->strategies()[0])->toBeInstanceOf(DirectJsonStrategy::class);
});
