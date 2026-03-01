<?php declare(strict_types=1);

use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\TypeString\TypeString;

class RegressionKnownObject {}
enum RegressionKnownEnum: string { case A = 'a'; }

it('does not classify unknown tokens as object types', function () {
    expect(TypeString::fromString('unknown_type_token')->isObject())->toBeFalse()
        ->and(TypeString::fromString('?unknown_type_token')->isObject())->toBeFalse();
});

it('keeps positive object evidence behavior for valid classes and enums', function () {
    expect(TypeString::fromString(RegressionKnownObject::class)->isObject())->toBeTrue()
        ->and(TypeString::fromString(RegressionKnownEnum::class)->isObject())->toBeTrue()
        ->and(TypeString::fromString('object')->isObject())->toBeTrue();
});

it('routes unknown type names through unsupported fallback in TypeDetailsFactory path', function () {
    expect(fn() => TypeDetails::fromTypeName('unknown_type_token'))
        ->toThrow(Exception::class, 'Unsupported type');
});
