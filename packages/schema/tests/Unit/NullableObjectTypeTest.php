<?php declare(strict_types=1);

use Cognesy\Schema\Reflection\PropertyInfo;
use Cognesy\Schema\Data\TypeDetails;

// Fixture classes for testing nullable object types
class UserDetail {
    public int $age;
    public string $name;
    public ?string $role = null;
}

class MaybeUser {
    public ?UserDetail $result = null;
    public ?string $errorMessage = '';
    public bool $error = false;
}

class NullableNestedObject {
    public ?UserDetail $user = null;
    public ?MaybeUser $maybeUser = null;
}

it('resolves nullable object types without calling getClassName() on NullableType', function () {
    $type = PropertyInfo::fromName(MaybeUser::class, 'result')->getTypeDetails();
    expect($type)->toBeInstanceOf(TypeDetails::class);

    $str = (string) $type;
    // Should resolve to 'UserDetail' not throw an error
    expect($str)->toBe('UserDetail');
});

it('handles nullable string types correctly', function () {
    $type = PropertyInfo::fromName(MaybeUser::class, 'errorMessage')->getTypeDetails();
    expect($type)->toBeInstanceOf(TypeDetails::class);

    $str = (string) $type;
    expect($str)->toBe('string');
});

it('handles non-nullable bool types correctly', function () {
    $type = PropertyInfo::fromName(MaybeUser::class, 'error')->getTypeDetails();
    expect($type)->toBeInstanceOf(TypeDetails::class);

    $str = (string) $type;
    expect($str)->toBe('bool');
});

it('detects nullable property correctly for object types', function () {
    $isNullable = PropertyInfo::fromName(MaybeUser::class, 'result')->isNullable();
    expect($isNullable)->toBeTrue();
});

it('detects nullable property correctly for scalar types', function () {
    $isNullable = PropertyInfo::fromName(MaybeUser::class, 'errorMessage')->isNullable();
    expect($isNullable)->toBeTrue();
});

it('detects non-nullable property correctly', function () {
    $isNullable = PropertyInfo::fromName(MaybeUser::class, 'error')->isNullable();
    expect($isNullable)->toBeFalse();
});

it('resolves nested nullable object types', function () {
    $type = PropertyInfo::fromName(NullableNestedObject::class, 'user')->getTypeDetails();
    expect($type)->toBeInstanceOf(TypeDetails::class);

    $str = (string) $type;
    expect($str)->toBe('UserDetail');
});

it('resolves nullable object containing nullable object', function () {
    $type = PropertyInfo::fromName(NullableNestedObject::class, 'maybeUser')->getTypeDetails();
    expect($type)->toBeInstanceOf(TypeDetails::class);

    $str = (string) $type;
    expect($str)->toBe('MaybeUser');
});
