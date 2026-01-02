<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Feature\Instructor;

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\MockHttp;

/**
 * Test-first validation for intoArray() flow.
 *
 * These tests validate design assumptions BEFORE implementation.
 * They should fail until the fluent API methods are implemented.
 */

// Test fixture
class IntoArrayUser
{
    public function __construct(
        public string $name = '',
        public int $age = 0,
    ) {}
}

class IntoArrayUserDTO
{
    public function __construct(
        public string $name = '',
        public int $age = 0,
    ) {}
}

$mockHttp = MockHttp::get([
    '{"name":"John","age":30}'
]);

it('returns array when intoArray() is called', function () use ($mockHttp) {
    $text = "His name is John, he is 30 years old.";

    $result = (new StructuredOutput())
        ->withHttpClient($mockHttp)
        ->withResponseClass(IntoArrayUser::class)
        ->intoArray()
        ->with(messages: [['role' => 'user', 'content' => $text]])
        ->get();

    expect($result)->toBeArray();
    expect($result)->toBe(['name' => 'John', 'age' => 30]);
});

it('schema comes from class, output is array', function () use ($mockHttp) {
    $text = "His name is John, he is 30 years old.";

    // The schema sent to LLM is from IntoArrayUser::class
    // But the return type is array
    $result = (new StructuredOutput())
        ->withHttpClient($mockHttp)
        ->withResponseClass(IntoArrayUser::class)
        ->intoArray()
        ->with(messages: [['role' => 'user', 'content' => $text]])
        ->get();

    expect($result)->toBeArray();
    expect($result)->toHaveKey('name');
    expect($result)->toHaveKey('age');
    expect($result['name'])->toBe('John');
    expect($result['age'])->toBe(30);
});

it('returns object when intoArray() is NOT called (backward compatible)', function () use ($mockHttp) {
    $text = "His name is John, he is 30 years old.";

    $result = (new StructuredOutput())
        ->withHttpClient($mockHttp)
        ->with(
            messages: [['role' => 'user', 'content' => $text]],
            responseModel: IntoArrayUser::class,
        )
        ->get();

    expect($result)->toBeInstanceOf(IntoArrayUser::class);
    expect($result->name)->toBe('John');
    expect($result->age)->toBe(30);
});

it('allows different target class with intoInstanceOf()', function () use ($mockHttp) {
    $text = "His name is John, he is 30 years old.";

    // Schema from IntoArrayUser, but deserialize to IntoArrayUserDTO
    $result = (new StructuredOutput())
        ->withHttpClient($mockHttp)
        ->withResponseClass(IntoArrayUser::class)
        ->intoInstanceOf(IntoArrayUserDTO::class)
        ->with(messages: [['role' => 'user', 'content' => $text]])
        ->get();

    expect($result)->toBeInstanceOf(IntoArrayUserDTO::class);
    expect($result->name)->toBe('John');
    expect($result->age)->toBe(30);
});

it('getArray() works when used with intoArray()', function () use ($mockHttp) {
    $text = "His name is John, he is 30 years old.";

    // getArray() requires result to already be an array (via intoArray())
    $result = (new StructuredOutput())
        ->withHttpClient($mockHttp)
        ->withResponseClass(IntoArrayUser::class)
        ->intoArray()
        ->with(
            messages: [['role' => 'user', 'content' => $text]],
        )
        ->getArray();

    expect($result)->toBeArray();
    expect($result['name'])->toBe('John');
    expect($result['age'])->toBe(30);
});

it('intoArray() works with JSON Schema mode', function () use ($mockHttp) {
    $text = "His name is John, he is 30 years old.";

    $result = (new StructuredOutput())
        ->withHttpClient($mockHttp)
        ->withResponseClass(IntoArrayUser::class)
        ->intoArray()
        ->with(
            messages: [['role' => 'user', 'content' => $text]],
            mode: \Cognesy\Polyglot\Inference\Enums\OutputMode::JsonSchema,
        )
        ->get();

    expect($result)->toBeArray();
    expect($result)->toBe(['name' => 'John', 'age' => 30]);
});

it('intoArray() preserves all data types', function () use ($mockHttp) {
    $text = "His name is John, he is 30 years old.";

    $result = (new StructuredOutput())
        ->withHttpClient($mockHttp)
        ->withResponseClass(IntoArrayUser::class)
        ->intoArray()
        ->with(messages: [['role' => 'user', 'content' => $text]])
        ->get();

    expect($result)->toBeArray();
    expect($result['name'])->toBeString();
    expect($result['age'])->toBeInt();
});

it('streaming with intoArray() returns array as final value', function () {
    // SSE payloads simulate streaming JSON
    $payloads = [
        ['choices' => [['delta' => ['content' => '{"name":"Jo']]]],
        ['choices' => [['delta' => ['content' => 'hn","age":30}']]]],
    ];

    $http = (new \Cognesy\Http\Creation\HttpClientBuilder())
        ->withMock(function ($mock) use ($payloads) {
            $mock->on()
                ->post('https://api.openai.com/v1/chat/completions')
                ->withStream(true)
                ->withJsonSubset(['stream' => true])
                ->replySSEFromJson($payloads);
        })
        ->create();

    $result = (new StructuredOutput())
        ->withHttpClient($http)
        ->using('openai')
        ->withResponseClass(IntoArrayUser::class)
        ->intoArray()
        ->with(
            messages: 'Extract user info',
            model: 'gpt-4o-mini',
            mode: \Cognesy\Polyglot\Inference\Enums\OutputMode::Json,
        )
        ->withStreaming(true)
        ->stream()
        ->finalValue();

    expect($result)->toBeArray();
    expect($result['name'])->toBe('John');
    expect($result['age'])->toBe(30);
});

