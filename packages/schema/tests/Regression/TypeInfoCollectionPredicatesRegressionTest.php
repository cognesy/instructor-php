<?php declare(strict_types=1);

use Cognesy\Schema\TypeInfo;

enum TypeInfoCollectionPredicatesEnum : string
{
    case A = 'a';
}

final class TypeInfoCollectionPredicatesObject
{
    public function __construct(
        public string $value = '',
    ) {}
}

it('does not treat collection wrappers as enum or object roots', function () {
    $enumListType = TypeInfo::fromTypeName('list<' . TypeInfoCollectionPredicatesEnum::class . '>');
    $objectListType = TypeInfo::fromTypeName('list<' . TypeInfoCollectionPredicatesObject::class . '>');
    $objectArrayType = TypeInfo::fromTypeName('array<string, ' . TypeInfoCollectionPredicatesObject::class . '>');

    expect(TypeInfo::isCollection($enumListType))->toBeTrue();
    expect(TypeInfo::isCollection($objectListType))->toBeTrue();
    expect(TypeInfo::isCollection($objectArrayType))->toBeTrue();

    expect(TypeInfo::isEnum($enumListType))->toBeFalse();
    expect(TypeInfo::isObject($enumListType))->toBeFalse();
    expect(TypeInfo::isEnum($objectListType))->toBeFalse();
    expect(TypeInfo::isObject($objectListType))->toBeFalse();
    expect(TypeInfo::isObject($objectArrayType))->toBeFalse();
});

it('still detects enum and object at root level', function () {
    $enumType = TypeInfo::fromTypeName(TypeInfoCollectionPredicatesEnum::class);
    $objectType = TypeInfo::fromTypeName(TypeInfoCollectionPredicatesObject::class);

    expect(TypeInfo::isEnum($enumType))->toBeTrue();
    expect(TypeInfo::isObject($enumType))->toBeFalse();

    expect(TypeInfo::isObject($objectType))->toBeTrue();
    expect(TypeInfo::isEnum($objectType))->toBeFalse();
});
