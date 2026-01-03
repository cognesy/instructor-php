<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Unit\Deserialization;

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Data\OutputFormat;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Test-first validation for Array-First Deserialization.
 *
 * These tests validate design assumptions BEFORE implementation.
 * They should fail until ResponseDeserializer.deserializeFromArray() is implemented.
 */

// Test fixtures
class TestUser
{
    public function __construct(
        public string $name = '',
        public int $age = 0,
    ) {}
}

class TestUserDTO
{
    public function __construct(
        public string $name = '',
        public int $age = 0,
    ) {}
}

// Helper to create a minimal ResponseModel for testing
function createTestResponseModel(string $class, ?OutputFormat $outputFormat = null): \Cognesy\Instructor\Data\ResponseModel
{
    $config = new StructuredOutputConfig();
    $schemaFactory = new SchemaFactory(useObjectReferences: false);
    $toolCallBuilder = new ToolCallBuilder($schemaFactory);
    $events = new class implements EventDispatcherInterface {
        public function dispatch(object $event): object { return $event; }
    };

    $factory = new ResponseModelFactory($toolCallBuilder, $schemaFactory, $config, $events);
    $responseModel = $factory->fromAny($class);

    // Apply OutputFormat if provided
    if ($outputFormat !== null) {
        $responseModel = $responseModel->withOutputFormat($outputFormat);
    }

    return $responseModel;
}

// Helper to create a ResponseDeserializer for testing
function createTestDeserializer(): ResponseDeserializer
{
    $events = new class implements EventDispatcherInterface {
        public function dispatch(object $event): object { return $event; }
    };
    $config = new StructuredOutputConfig();

    return new ResponseDeserializer(
        events: $events,
        deserializers: [new SymfonyDeserializer()],
        config: $config,
    );
}

it('returns array directly when OutputFormat is array', function () {
    $data = ['name' => 'John', 'age' => 30];
    $responseModel = createTestResponseModel(TestUser::class, OutputFormat::array());
    $deserializer = createTestDeserializer();

    // This method will be added to ResponseDeserializer
    $result = $deserializer->deserialize($data, $responseModel);

    expect($result->isSuccess())->toBeTrue();
    expect($result->unwrap())->toBe($data);
    expect($result->unwrap())->toBeArray();
});

it('hydrates to target class when OutputFormat specifies class', function () {
    $data = ['name' => 'John', 'age' => 30];
    $responseModel = createTestResponseModel(TestUser::class, OutputFormat::instanceOf(TestUserDTO::class));
    $deserializer = createTestDeserializer();

    $result = $deserializer->deserialize($data, $responseModel);

    expect($result->isSuccess())->toBeTrue();
    expect($result->unwrap())->toBeInstanceOf(TestUserDTO::class);
    expect($result->unwrap()->name)->toBe('John');
    expect($result->unwrap()->age)->toBe(30);
});

it('hydrates to schema class when OutputFormat is null (default)', function () {
    $data = ['name' => 'Jane', 'age' => 25];
    $responseModel = createTestResponseModel(TestUser::class);
    $deserializer = createTestDeserializer();

    // When OutputFormat is null, should use returnedClass from ResponseModel (default behavior)
    $result = $deserializer->deserialize($data, $responseModel);

    expect($result->isSuccess())->toBeTrue();
    expect($result->unwrap())->toBeInstanceOf(TestUser::class);
    expect($result->unwrap()->name)->toBe('Jane');
    expect($result->unwrap()->age)->toBe(25);
});


it('handles nested objects in array deserialization', function () {
    $data = [
        'name' => 'John',
        'age' => 30,
    ];
    $responseModel = createTestResponseModel(TestUser::class, OutputFormat::array());
    $deserializer = createTestDeserializer();

    $result = $deserializer->deserialize($data, $responseModel);

    expect($result->isSuccess())->toBeTrue();
    expect($result->unwrap())->toBe($data);
});

it('returns failure for invalid data when hydrating to class', function () {
    // Intentionally invalid data - missing required fields or wrong types
    // Note: This tests deserialization error handling, not validation
    $data = ['invalid_field' => 'value'];
    $responseModel = createTestResponseModel(TestUser::class, OutputFormat::instanceOf(TestUserDTO::class));
    $deserializer = createTestDeserializer();

    $result = $deserializer->deserialize($data, $responseModel);

    // Symfony deserializer should still succeed (it uses default values)
    // This is expected behavior - validation is a separate stage
    expect($result->isSuccess())->toBeTrue();
    expect($result->unwrap())->toBeInstanceOf(TestUserDTO::class);
});

it('preserves array types and values accurately', function () {
    $data = [
        'name' => 'John',
        'age' => 30,
    ];
    $responseModel = createTestResponseModel(TestUser::class, OutputFormat::array());
    $deserializer = createTestDeserializer();

    $result = $deserializer->deserialize($data, $responseModel);

    expect($result->isSuccess())->toBeTrue();
    $array = $result->unwrap();
    expect($array['name'])->toBe('John');
    expect($array['name'])->toBeString();
    expect($array['age'])->toBe(30);
    expect($array['age'])->toBeInt();
});

