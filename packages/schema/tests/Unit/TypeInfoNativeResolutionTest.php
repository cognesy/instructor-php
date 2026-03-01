<?php declare(strict_types=1);

use Cognesy\Schema\Exceptions\TypeResolutionException;
use Cognesy\Schema\Reflection\PropertyInfo;
use Cognesy\Schema\TypeInfo;
use Symfony\Component\TypeInfo\Type;

class TypeInfoFixture
{
    /** @var array<int, string>|null */
    public ?array $nullableIntStringArray = null;

    /** @var iterable<float> */
    public iterable $iterableOfFloats = [];

    /** @var array<array<int>> */
    public array $arrayOfArraysOfInts = [];
}

it('resolves nullable collection property via TypeInfo', function () {
    $property = PropertyInfo::fromName(TypeInfoFixture::class, 'nullableIntStringArray');

    expect($property->isNullable())->toBeTrue();
    expect((string) $property->getType())->toBe('array<int, string>');
});

it('resolves iterable of floats as float collection', function () {
    $property = PropertyInfo::fromName(TypeInfoFixture::class, 'iterableOfFloats');

    expect((string) $property->getType())->toBe('iterable<int|string, float>');
});

it('normalizes nested arrays to array type', function () {
    $property = PropertyInfo::fromName(TypeInfoFixture::class, 'arrayOfArraysOfInts');

    expect((string) $property->getType())->toBe('array<int|string, array<int|string, int>>');
});

it('keeps union policy explicit for scalar unions', function () {
    $widened = TypeInfo::fromTypeName('int|float');
    $fallback = TypeInfo::fromTypeName('int|string');

    expect((string) $widened)->toBe((string) Type::float());
    expect((string) $fallback)->toBe((string) Type::mixed());
});

it('rejects multi-branch non-scalar unions', function () {
    expect(fn() => TypeInfo::fromTypeName(DateTimeImmutable::class . '|' . DateTime::class))
        ->toThrow(TypeResolutionException::class, 'Union types with multiple non-null branches are not supported');
});
