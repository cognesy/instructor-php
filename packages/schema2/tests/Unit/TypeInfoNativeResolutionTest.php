<?php declare(strict_types=1);

use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Reflection\PropertyInfo;

class Schema2TypeInfoFixture
{
    /** @var array<int, string>|null */
    public ?array $nullableIntStringArray = null;

    /** @var iterable<float> */
    public iterable $iterableOfFloats = [];

    /** @var array<array<int>> */
    public array $arrayOfArraysOfInts = [];
}

it('resolves nullable collection property via TypeInfo adapter', function () {
    $property = PropertyInfo::fromName(Schema2TypeInfoFixture::class, 'nullableIntStringArray');

    expect($property->isNullable())->toBeTrue();
    expect((string) $property->getTypeDetails())->toBe('string[]');
});

it('resolves iterable of floats as float collection', function () {
    $property = PropertyInfo::fromName(Schema2TypeInfoFixture::class, 'iterableOfFloats');

    expect((string) $property->getTypeDetails())->toBe('float[]');
});

it('normalizes nested arrays to array type', function () {
    $property = PropertyInfo::fromName(Schema2TypeInfoFixture::class, 'arrayOfArraysOfInts');

    expect((string) $property->getTypeDetails())->toBe('array');
});

it('keeps union policy explicit for scalar unions', function () {
    $widened = TypeDetails::fromTypeName('int|float');
    $fallback = TypeDetails::fromTypeName('int|string');

    expect($widened->type())->toBe(TypeDetails::PHP_FLOAT);
    expect($fallback->type())->toBe(TypeDetails::PHP_MIXED);
});

it('rejects multi-branch non-scalar unions', function () {
    expect(fn() => TypeDetails::fromTypeName(DateTimeImmutable::class . '|' . DateTime::class))
        ->toThrow(Exception::class, 'Union types with multiple non-null branches are not supported');
});

it('does not expose legacy Symfony 6 adapter class', function () {
    expect(class_exists(Cognesy\Schema\Utils\Compat\PropertyInfoV6Adapter::class))->toBeFalse();
});
