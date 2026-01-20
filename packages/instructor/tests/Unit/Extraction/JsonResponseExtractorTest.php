<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Unit\Extraction;

use Cognesy\Instructor\Extraction\ResponseExtractor;
use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\Extraction\Exceptions\ExtractionException;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

/**
 * Test-first validation for ResponseExtractor.
 *
 * These tests validate design assumptions BEFORE implementation.
 * They should fail until ResponseExtractor is implemented.
 */

it('extracts array from valid JSON content', function () {
    $response = new InferenceResponse(content: '{"name":"John","age":30}');
    $extractor = new ResponseExtractor();

    $result = $extractor->extract(ExtractionInput::fromResponse($response, OutputMode::Json));

    expect($result)->toBe(['name' => 'John', 'age' => 30]);
});

it('extracts array from JSON Schema mode', function () {
    $response = new InferenceResponse(content: '{"name":"Jane","age":25}');
    $extractor = new ResponseExtractor();

    $result = $extractor->extract(ExtractionInput::fromResponse($response, OutputMode::JsonSchema));

    expect($result)->toBe(['name' => 'Jane', 'age' => 25]);
});

it('extracts from tool calls in Tools mode', function () {
    $toolCalls = new ToolCalls(
        new ToolCall(name: 'User', args: ['name' => 'John', 'age' => 30])
    );
    $response = new InferenceResponse(content: '', toolCalls: $toolCalls);
    $extractor = new ResponseExtractor();

    $result = $extractor->extract(ExtractionInput::fromResponse($response, OutputMode::Tools));

    expect($result)->toBe(['name' => 'John', 'age' => 30]);
});

it('falls back to content in Tools mode when tool calls are missing', function () {
    $response = new InferenceResponse(content: '{"name":"John","age":30}');
    $extractor = new ResponseExtractor();

    $result = $extractor->extract(ExtractionInput::fromResponse($response, OutputMode::Tools));

    expect($result)->toBe(['name' => 'John', 'age' => 30]);
});

it('handles markdown-wrapped JSON', function () {
    $content = <<<'MD'
Here is the extracted data:

```json
{"name":"John","age":30}
```

This represents the user.
MD;

    $response = new InferenceResponse(content: $content);
    $extractor = new ResponseExtractor();

    $result = $extractor->extract(ExtractionInput::fromResponse($response, OutputMode::Json));

    expect($result)->toBe(['name' => 'John', 'age' => 30]);
});

it('handles JSON wrapped in text (bracket matching)', function () {
    $content = 'The user data is {"name":"John","age":30} as extracted.';
    $response = new InferenceResponse(content: $content);
    $extractor = new ResponseExtractor();

    $result = $extractor->extract(ExtractionInput::fromResponse($response, OutputMode::Json));

    expect($result)->toBe(['name' => 'John', 'age' => 30]);
});

it('handles nested objects', function () {
    $json = '{"user":{"name":"John","address":{"city":"NYC","zip":"10001"}},"active":true}';
    $response = new InferenceResponse(content: $json);
    $extractor = new ResponseExtractor();

    $result = $extractor->extract(ExtractionInput::fromResponse($response, OutputMode::Json));

    expect($result)->toBe([
        'user' => [
            'name' => 'John',
            'address' => [
                'city' => 'NYC',
                'zip' => '10001',
            ],
        ],
        'active' => true,
    ]);
});

it('handles arrays in JSON', function () {
    $json = '{"users":[{"name":"John"},{"name":"Jane"}],"count":2}';
    $response = new InferenceResponse(content: $json);
    $extractor = new ResponseExtractor();

    $result = $extractor->extract(ExtractionInput::fromResponse($response, OutputMode::Json));

    expect($result)->toBe([
        'users' => [
            ['name' => 'John'],
            ['name' => 'Jane'],
        ],
        'count' => 2,
    ]);
});

it('returns failure for invalid JSON', function () {
    $response = new InferenceResponse(content: 'this is not json at all');
    $extractor = new ResponseExtractor();

    $input = ExtractionInput::fromResponse($response, OutputMode::Json);
    expect(fn() => $extractor->extract($input))->toThrow(ExtractionException::class);
});

it('returns failure for empty content', function () {
    $response = new InferenceResponse(content: '');
    $extractor = new ResponseExtractor();

    $input = ExtractionInput::fromResponse($response, OutputMode::Json);
    expect(fn() => $extractor->extract($input))->toThrow(ExtractionException::class);
});

it('handles malformed JSON with trailing comma (resilient parsing)', function () {
    // Note: This tests the existing resilient parser behavior
    $response = new InferenceResponse(content: '{"name":"John","age":30,}');
    $extractor = new ResponseExtractor();

    $result = $extractor->extract(ExtractionInput::fromResponse($response, OutputMode::Json));

    // Resilient parser should fix trailing comma
    expect($result)->toBe(['name' => 'John', 'age' => 30]);
});

it('handles MdJson mode (markdown with JSON)', function () {
    $response = new InferenceResponse(content: '```json
{"name":"John"}
```');
    $extractor = new ResponseExtractor();

    $result = $extractor->extract(ExtractionInput::fromResponse($response, OutputMode::MdJson));

    expect($result)->toBe(['name' => 'John']);
});
