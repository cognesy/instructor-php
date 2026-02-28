<?php declare(strict_types=1);

use Cognesy\Schema\Data\TypeDetails;
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

it('resolves property types with explicit compatibility-adapter expectations', function (string $property, string $expectedType) {
    $type = PropertyInfo::fromName(PI_Fixture::class, $property)->getTypeDetails();

    expect($type)->toBeInstanceOf(TypeDetails::class);
    expect((string)$type)->toBe($expectedType);
})->with(function (): array {
    // Keep expectations explicit and adapter-version aware.
    return match (class_exists(Type::class)) {
        true => [
            'nullableArrayOfObjects' => ['nullableArrayOfObjects', 'DateTimeImmutable[]'],
            'iterableOfFloats' => ['iterableOfFloats', 'float[]'],
            'arrayOfArraysOfInts' => ['arrayOfArraysOfInts', 'array'],
            'nullableIntStringArray' => ['nullableIntStringArray', 'string[]'],
            'mapOfNestedArrays' => ['mapOfNestedArrays', 'array'],
        ],
        false => [
            'nullableArrayOfObjects' => ['nullableArrayOfObjects', 'DateTimeImmutable[]'],
            'iterableOfFloats' => ['iterableOfFloats', 'float[]'],
            'arrayOfArraysOfInts' => ['arrayOfArraysOfInts', 'array'],
            'nullableIntStringArray' => ['nullableIntStringArray', 'string[]'],
            'mapOfNestedArrays' => ['mapOfNestedArrays', 'array'],
        ],
    };
});
