<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Unit\Extraction;

use Cognesy\Instructor\Extraction\JsonResponseExtractor;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

/**
 * Test-first validation for JsonResponseExtractor.
 *
 * These tests validate design assumptions BEFORE implementation.
 * They should fail until JsonResponseExtractor is implemented.
 */

it('extracts array from valid JSON content', function () {
    $response = new InferenceResponse(content: '{"name":"John","age":30}');
    $extractor = new JsonResponseExtractor();

    $result = $extractor->extract($response, OutputMode::Json);

    expect($result->isSuccess())->toBeTrue();
    expect($result->unwrap())->toBe(['name' => 'John', 'age' => 30]);
});

it('extracts array from JSON Schema mode', function () {
    $response = new InferenceResponse(content: '{"name":"Jane","age":25}');
    $extractor = new JsonResponseExtractor();

    $result = $extractor->extract($response, OutputMode::JsonSchema);

    expect($result->isSuccess())->toBeTrue();
    expect($result->unwrap())->toBe(['name' => 'Jane', 'age' => 25]);
});

it('extracts from tool calls in Tools mode', function () {
    $toolCalls = new ToolCalls(
        new ToolCall(name: 'User', args: ['name' => 'John', 'age' => 30])
    );
    $response = new InferenceResponse(content: '', toolCalls: $toolCalls);
    $extractor = new JsonResponseExtractor();

    $result = $extractor->extract($response, OutputMode::Tools);

    expect($result->isSuccess())->toBeTrue();
    expect($result->unwrap())->toBe(['name' => 'John', 'age' => 30]);
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
    $extractor = new JsonResponseExtractor();

    $result = $extractor->extract($response, OutputMode::Json);

    expect($result->isSuccess())->toBeTrue();
    expect($result->unwrap())->toBe(['name' => 'John', 'age' => 30]);
});

it('handles JSON wrapped in text (bracket matching)', function () {
    $content = 'The user data is {"name":"John","age":30} as extracted.';
    $response = new InferenceResponse(content: $content);
    $extractor = new JsonResponseExtractor();

    $result = $extractor->extract($response, OutputMode::Json);

    expect($result->isSuccess())->toBeTrue();
    expect($result->unwrap())->toBe(['name' => 'John', 'age' => 30]);
});

it('handles nested objects', function () {
    $json = '{"user":{"name":"John","address":{"city":"NYC","zip":"10001"}},"active":true}';
    $response = new InferenceResponse(content: $json);
    $extractor = new JsonResponseExtractor();

    $result = $extractor->extract($response, OutputMode::Json);

    expect($result->isSuccess())->toBeTrue();
    expect($result->unwrap())->toBe([
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
    $extractor = new JsonResponseExtractor();

    $result = $extractor->extract($response, OutputMode::Json);

    expect($result->isSuccess())->toBeTrue();
    expect($result->unwrap())->toBe([
        'users' => [
            ['name' => 'John'],
            ['name' => 'Jane'],
        ],
        'count' => 2,
    ]);
});

it('returns failure for invalid JSON', function () {
    $response = new InferenceResponse(content: 'this is not json at all');
    $extractor = new JsonResponseExtractor();

    $result = $extractor->extract($response, OutputMode::Json);

    expect($result->isFailure())->toBeTrue();
});

it('returns failure for empty content', function () {
    $response = new InferenceResponse(content: '');
    $extractor = new JsonResponseExtractor();

    $result = $extractor->extract($response, OutputMode::Json);

    expect($result->isFailure())->toBeTrue();
});

it('handles malformed JSON with trailing comma (resilient parsing)', function () {
    // Note: This tests the existing resilient parser behavior
    $response = new InferenceResponse(content: '{"name":"John","age":30,}');
    $extractor = new JsonResponseExtractor();

    $result = $extractor->extract($response, OutputMode::Json);

    // Resilient parser should fix trailing comma
    expect($result->isSuccess())->toBeTrue();
    expect($result->unwrap())->toBe(['name' => 'John', 'age' => 30]);
});

it('handles MdJson mode (markdown with JSON)', function () {
    $response = new InferenceResponse(content: '```json
{"name":"John"}
```');
    $extractor = new JsonResponseExtractor();

    $result = $extractor->extract($response, OutputMode::MdJson);

    expect($result->isSuccess())->toBeTrue();
    expect($result->unwrap())->toBe(['name' => 'John']);
});
