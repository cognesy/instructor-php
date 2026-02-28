<?php declare(strict_types=1);

use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Tests\Examples\RefsCollision\NA\User as NAUser;
use Cognesy\Schema\Tests\Examples\RefsCollision\NB\User as NBUser;
use Cognesy\Schema\Tests\Examples\Schema\SimpleClass;

// Guards regression from instructor-ud9f (silent union narrowing to first branch).
it('widens numeric scalar unions in fromTypeName', function () {
    $type = TypeDetails::fromTypeName('int|float');

    expect($type->type())->toBe(TypeDetails::PHP_FLOAT);
});

it('rejects non-null object unions in fromTypeName', function () {
    expect(fn() => TypeDetails::fromTypeName(NAUser::class . '|' . NBUser::class))
        ->toThrow(Exception::class, 'Union types with multiple non-null branches are not supported');
});

it('falls back to mixed for non-numeric scalar unions in fromTypeName', function () {
    $type = TypeDetails::fromTypeName('int|string');

    expect($type->type())->toBe(TypeDetails::PHP_MIXED);
});

it('falls back to mixed for non-numeric scalar unions in fromPhpDocTypeString', function () {
    $type = TypeDetails::fromPhpDocTypeString('int|string');

    expect($type->type())->toBe(TypeDetails::PHP_MIXED);
});

it('rejects non-null object unions in fromPhpDocTypeString', function () {
    expect(fn() => TypeDetails::fromPhpDocTypeString(NAUser::class . '|' . NBUser::class))
        ->toThrow(Exception::class, 'Union types with multiple non-null branches are not supported');
});

it('keeps nullable unions supported in fromTypeName', function () {
    $type = TypeDetails::fromTypeName(SimpleClass::class . '|null');

    expect($type->type())->toBe(TypeDetails::PHP_OBJECT);
    expect($type->class())->toBe(SimpleClass::class);
});
