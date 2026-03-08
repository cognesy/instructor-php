<?php

declare(strict_types=1);

use Cognesy\AgentCtrl\Common\Value\Normalize;

it('normalizes strings, scalars and complex values consistently', function () {
    expect(Normalize::toString('abc'))->toBe('abc')
        ->and(Normalize::toString(12))->toBe('12')
        ->and(Normalize::toString(1.5))->toBe('1.5')
        ->and(Normalize::toString(true))->toBe('true')
        ->and(Normalize::toString(false))->toBe('false')
        ->and(Normalize::toString(['a' => 1]))->toBe('{"a":1}')
        ->and(Normalize::toString(null, 'x'))->toBe('x')
        ->and(Normalize::toNullableString(null))->toBeNull()
        ->and(Normalize::toNullableString(['a' => 1]))->toBe('{"a":1}');
});

it('normalizes bool, int and float values', function () {
    expect(Normalize::toBool('true'))->toBeTrue()
        ->and(Normalize::toBool('0'))->toBeFalse()
        ->and(Normalize::toBool('invalid', true))->toBeTrue()
        ->and(Normalize::toInt('12'))->toBe(12)
        ->and(Normalize::toInt('abc', 7))->toBe(7)
        ->and(Normalize::toNullableInt(null))->toBeNull()
        ->and(Normalize::toNullableInt('4'))->toBe(4)
        ->and(Normalize::toFloat('2.5'))->toBe(2.5)
        ->and(Normalize::toFloat('abc', 3.1))->toBe(3.1);
});

it('normalizes arrays only when value is an array', function () {
    expect(Normalize::toArray(['a' => 1]))->toBe(['a' => 1])
        ->and(Normalize::toArray('invalid'))->toBe([]);
});
