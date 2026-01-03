<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Unit\Data;

use Cognesy\Instructor\Data\OutputFormat;
use Cognesy\Instructor\Enums\OutputFormatType;

/**
 * Test-first validation for OutputFormat value object.
 *
 * These tests validate design assumptions BEFORE implementation.
 * They should fail until OutputFormat is implemented.
 */

it('creates array format', function () {
    $format = OutputFormat::array();

    expect($format->isArray())->toBeTrue();
    expect($format->isClass())->toBeFalse();
    expect($format->isObject())->toBeFalse();
    expect($format->targetClass())->toBeNull();
    expect($format->targetInstance())->toBeNull();
});

it('creates class format with target class', function () {
    $format = OutputFormat::instanceOf(TestUser::class);

    expect($format->isArray())->toBeFalse();
    expect($format->isClass())->toBeTrue();
    expect($format->isObject())->toBeFalse();
    expect($format->targetClass())->toBe(TestUser::class);
    expect($format->targetInstance())->toBeNull();
});

it('creates self-deserializing format with instance', function () {
    $instance = new TestSelfDeserializing();
    $format = OutputFormat::selfDeserializing($instance);

    expect($format->isArray())->toBeFalse();
    expect($format->isClass())->toBeFalse();
    expect($format->isObject())->toBeTrue();
    expect($format->targetClass())->toBe(TestSelfDeserializing::class);
    expect($format->targetInstance())->toBe($instance);
});

it('is immutable value object - array formats are equal', function () {
    $format1 = OutputFormat::array();
    $format2 = OutputFormat::array();

    expect($format1)->toEqual($format2);
});

it('is immutable value object - class formats with same class are equal', function () {
    $format1 = OutputFormat::instanceOf(TestUser::class);
    $format2 = OutputFormat::instanceOf(TestUser::class);

    expect($format1)->toEqual($format2);
});

it('class formats with different classes are not equal', function () {
    $format1 = OutputFormat::instanceOf(TestUser::class);
    $format2 = OutputFormat::instanceOf(TestUserDTO::class);

    expect($format1)->not->toEqual($format2);
});

it('array and class formats are not equal', function () {
    $format1 = OutputFormat::array();
    $format2 = OutputFormat::instanceOf(TestUser::class);

    expect($format1)->not->toEqual($format2);
});

it('exposes type property for debugging', function () {
    expect(OutputFormat::array()->type)->toBe(OutputFormatType::AsArray);
    expect(OutputFormat::instanceOf(TestUser::class)->type)->toBe(OutputFormatType::AsClass);
    expect(OutputFormat::selfDeserializing(new TestSelfDeserializing())->type)->toBe(OutputFormatType::AsObject);
});

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

class TestSelfDeserializing
{
    public mixed $value = null;
}
