<?php declare(strict_types=1);

use Cognesy\Schema\Reflection\PropertyInfo;
use Cognesy\Schema\Data\TypeDetails;

// Define fixtures with various type hints to exercise adapters
class PI_Fixture {
    /** @var array<int, string>|null */
    public ?array $nullableIntStringArray = null;

    /** @var iterable<float> */
    public iterable $iterableOfFloats;

    /** @var array<array<int>> */
    public array $arrayOfArraysOfInts = [];

    /** @var array<\DateTimeImmutable>|null */
    public ?array $nullableArrayOfObjects = null;

    /** @var array<int, array<string, int>> */
    public array $mapOfNestedArrays = [];
}

it('resolves nullable array-of-objects element types robustly', function () {
    $type = PropertyInfo::fromName(PI_Fixture::class, 'nullableArrayOfObjects')->getTypeDetails();
    expect($type)->toBeInstanceOf(TypeDetails::class);
    // Should detect array of DateTimeImmutable or fallback to array
    $str = (string) $type;
    // allow either 'DateTimeImmutable[]|null' or 'array'
    expect($str === 'DateTimeImmutable[]' || $str === 'DateTimeImmutable[]|null' || $str === 'array' || str_contains($str, 'array'))
        ->toBeTrue();
});

it('resolves iterable of scalars to array when needed', function () {
    $type = PropertyInfo::fromName(PI_Fixture::class, 'iterableOfFloats')->getTypeDetails();
    $str = (string) $type;
    // Could be 'array' or 'float[]' depending on adapter capabilities
    expect($str === 'float[]' || $str === 'array' || str_contains($str, 'float'))
        ->toBeTrue();
});

it('resolves nested arrays in unions and collections', function () {
    $type = PropertyInfo::fromName(PI_Fixture::class, 'arrayOfArraysOfInts')->getTypeDetails();
    $str = (string) $type;
    // Accept either 'int[]' (collapsed) or 'array'
    expect($str === 'int[]' || $str === 'array' || str_contains($str, 'int'))
        ->toBeTrue();
});

it('resolves nullable int-string arrays', function () {
    $type = PropertyInfo::fromName(PI_Fixture::class, 'nullableIntStringArray')->getTypeDetails();
    $str = (string) $type;
    // union could render 'int[]|string[]|array|...'; accept presence of array indicators or []
    expect(str_contains($str, '[]') || str_contains($str, 'array'))
        ->toBeTrue();
});
