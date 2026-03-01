<?php declare(strict_types=1);

use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Tests\Examples\Schema\SimpleClass;

// Guards regression from instructor-1mae (type-name normalization drift).
it('normalizes nullable object syntax in fromTypeName', function () {
    $type = TypeDetails::fromTypeName('?' . SimpleClass::class);

    expect($type->type())->toBe(TypeDetails::PHP_OBJECT);
    expect($type->class())->toBe(SimpleClass::class);
});

it('normalizes object|null union in fromTypeName', function () {
    $type = TypeDetails::fromTypeName(SimpleClass::class . '|null');

    expect($type->type())->toBe(TypeDetails::PHP_OBJECT);
    expect($type->class())->toBe(SimpleClass::class);
});

it('normalizes nullable collection syntax in fromTypeName', function () {
    $type = TypeDetails::fromTypeName('?int[]');

    expect($type->type())->toBe(TypeDetails::PHP_COLLECTION);
    expect($type->nestedType())->not->toBeNull();
    expect($type->nestedType()?->type())->toBe(TypeDetails::PHP_INT);
});

it('falls back to mixed for non-numeric scalar unions in fromTypeName', function () {
    $type = TypeDetails::fromTypeName('int|string');

    expect($type->type())->toBe(TypeDetails::PHP_MIXED);
});
