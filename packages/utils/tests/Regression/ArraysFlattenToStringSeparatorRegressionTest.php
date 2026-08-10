<?php declare(strict_types=1);

use Cognesy\Utils\Arrays;

/**
 * flattenToString() appended the separator after every part and then stripped the
 * trailing one with rtrim($flat, $separator). rtrim treats its second argument as a
 * SET OF CHARACTERS, not a suffix, so it chewed through any trailing content that
 * happened to be built from separator characters. With separator 'ab' the input
 * ['ab', 'ba'] produced 'ababba', every character of which is in the set, and the
 * function returned ''. Joining with implode removes the class of bug entirely.
 */

it('does not eat content whose characters appear in the separator', function () {
    expect(Arrays::flattenToString(['ab', 'ba'], 'ab'))->toBe('ababba');
});

it('keeps trailing characters that overlap a multi-character separator', function () {
    expect(Arrays::flattenToString(['a,', 'b'], ', '))->toBe('a,, b');
    expect(Arrays::flattenToString(['x-', 'y-'], '--'))->toBe('x---y-');
});

it('strips exactly one separator between parts and none at the ends', function () {
    expect(Arrays::flattenToString(['a', 'b', 'c'], '--'))->toBe('a--b--c');
    expect(Arrays::flattenToString(['only'], '--'))->toBe('only');
});

it('handles newline separators without collapsing blank-ish content', function () {
    expect(Arrays::flattenToString(['a', 'b'], "\n\n"))->toBe("a\n\nb");
});

it('preserves the documented behaviour for ordinary input', function () {
    $nested = ['apple', ['banana', 'orange'], ['grape', ['kiwi', 'mango']], 'pear'];
    expect(Arrays::flattenToString($nested, ','))
        ->toBe('apple,banana,orange,grape,kiwi,mango,pear');
});

it('still drops empty and whitespace-only leaves', function () {
    expect(Arrays::flattenToString(['hello', '', '   ', ['', 'foo'], 'bar'], ','))
        ->toBe('hello,foo,bar');
});

it('returns an empty string for an empty or all-empty input', function () {
    expect(Arrays::flattenToString([], ','))->toBe('');
    expect(Arrays::flattenToString([['', ''], ''], ','))->toBe('');
});

it('defaults to joining with no separator', function () {
    expect(Arrays::flattenToString(['a', ['b', 'c']]))->toBe('abc');
});
