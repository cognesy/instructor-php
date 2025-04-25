<?php

use Cognesy\Instructor\Features\Deserialization\Deserializers\BackedEnumNormalizer;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Cognesy\Instructor\Tests\Examples\Validators\MockBackedEnum;


test('it normalizes BackedEnum object to its value', function () {
    $normalizer = new BackedEnumNormalizer();
    $enum = MockBackedEnum::CASE_1;

    $normalizedValue = $normalizer->normalize($enum);

    expect($normalizedValue)->toBe('case_1');
});

test('it throws exception when normalizing non-BackedEnum object', function () {
    $normalizer = new BackedEnumNormalizer();
    $nonEnumObject = new stdClass();

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('The data must belong to a backed enumeration.');

    $normalizer->normalize($nonEnumObject);
});

test('it denormalizes valid value to BackedEnum instance', function () {
    $normalizer = new BackedEnumNormalizer();

    $denormalizedValue = $normalizer->denormalize('case_2', MockBackedEnum::class);

    expect($denormalizedValue)->toBeInstanceOf(MockBackedEnum::class);
    expect($denormalizedValue->value)->toBe('case_2');
});

test('it throws exception when denormalizing invalid value', function () {
    $normalizer = new BackedEnumNormalizer();

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('The value must one of: case_1, case_2');

    $normalizer->denormalize('invalid_value', MockBackedEnum::class);
});

test('it denormalizes null and invalid types to null when ALLOW_INVALID_VALUES is true', function () {
    $normalizer = new BackedEnumNormalizer();
    $context = [BackedEnumNormalizer::ALLOW_INVALID_VALUES => true];

    $nullValue = $normalizer->denormalize(null, MockBackedEnum::class, null, $context);
    $invalidTypeValue = $normalizer->denormalize(new stdClass(), MockBackedEnum::class, null, $context);

    expect($nullValue)->toBeNull();
    expect($invalidTypeValue)->toBeNull();
});

test('it throws exception when denormalizing non-BackedEnum type', function () {
    $normalizer = new BackedEnumNormalizer();

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('The data must belong to a backed enumeration.');

    $normalizer->denormalize('value', stdClass::class);
});
