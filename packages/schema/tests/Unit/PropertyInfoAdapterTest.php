<?php declare(strict_types=1);

use Cognesy\Schema\Reflection\PropertyInfo;
use Symfony\Component\TypeInfo\Type;

class PI_Fixture
{
    /** @var array<int, string>|null */
    public ?array $nullableIntStringArray = null;

    /** @var iterable<float> */
    public iterable $iterableOfFloats = [];

    /** @var array<array<int>> */
    public array $arrayOfArraysOfInts = [];

    /** @var array<\DateTimeImmutable>|null */
    public ?array $nullableArrayOfObjects = null;

    /** @var array<int, array<string, int>> */
    public array $mapOfNestedArrays = [];
}

it('resolves property types using Symfony TypeInfo representation', function (string $property, string $expectedType) {
    $type = PropertyInfo::fromName(PI_Fixture::class, $property)->getType();

    expect($type)->toBeInstanceOf(Type::class);
    expect((string)$type)->toBe($expectedType);
})->with(function (): array {
    return [
        'nullableArrayOfObjects' => ['nullableArrayOfObjects', 'array<int|string, DateTimeImmutable>'],
        'iterableOfFloats' => ['iterableOfFloats', 'iterable<int|string, float>'],
        'arrayOfArraysOfInts' => ['arrayOfArraysOfInts', 'array<int|string, array<int|string, int>>'],
        'nullableIntStringArray' => ['nullableIntStringArray', 'array<int, string>'],
        'mapOfNestedArrays' => ['mapOfNestedArrays', 'array<int, array<string, int>>'],
    ];
});
