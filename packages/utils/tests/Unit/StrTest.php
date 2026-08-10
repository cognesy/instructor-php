<?php declare(strict_types=1);

use Cognesy\Utils\Str;

/**
 * Behavioural cover for the Str helpers touched while simplifying contains()/when()
 * and rewriting startsWith()/endsWith() on top of the native predicates. The empty
 * needle regression itself is pinned in tests/Regression/StrEmptyNeedleRegressionTest.
 */

it('finds substrings case-sensitively by default', function () {
    expect(Str::contains('Hello World', 'World'))->toBeTrue();
    expect(Str::contains('Hello World', 'world'))->toBeFalse();
    expect(Str::contains('Hello World', 'world', caseSensitive: false))->toBeTrue();
    expect(Str::contains('Hello World', 'nope', caseSensitive: false))->toBeFalse();
});

it('treats an empty needle as always contained, in both modes', function () {
    expect(Str::contains('abc', ''))->toBeTrue();
    expect(Str::contains('abc', '', caseSensitive: false))->toBeTrue();
});

it('requires all needles for containsAll', function () {
    expect(Str::containsAll('one two three', ['one', 'three']))->toBeTrue();
    expect(Str::containsAll('one two three', ['one', 'four']))->toBeFalse();
    expect(Str::containsAll('one two', 'one'))->toBeTrue();
    expect(Str::containsAll('one two', ['ONE'], caseSensitive: false))->toBeTrue();
    expect(Str::containsAll('anything', []))->toBeTrue();
});

it('requires any needle for containsAny', function () {
    expect(Str::containsAny('one two', ['four', 'two']))->toBeTrue();
    expect(Str::containsAny('one two', ['four', 'five']))->toBeFalse();
    expect(Str::containsAny('one two', 'two'))->toBeTrue();
    expect(Str::containsAny('one two', ['TWO'], caseSensitive: false))->toBeTrue();
    expect(Str::containsAny('anything', []))->toBeFalse();
});

it('selects between two strings with when', function () {
    expect(Str::when(true, 'yes', 'no'))->toBe('yes');
    expect(Str::when(false, 'yes', 'no'))->toBe('no');
});

it('extracts the text between two needles', function () {
    expect(Str::between('a[b]c', '[', ']'))->toBe('b');
    expect(Str::between('no markers', '[', ']'))->toBe('');
    expect(Str::between('a[bc', '[', ']'))->toBe('');
});

it('extracts the text after a needle', function () {
    expect(Str::after('key=value', '='))->toBe('value');
    expect(Str::after('no separator', '='))->toBe('');
    expect(Str::after('a=b=c', '='))->toBe('b=c');
});

it('converts between casing conventions', function (string $input) {
    expect(Str::pascal($input))->toBe('CamelCaseString');
    expect(Str::camel($input))->toBe('camelCaseString');
    expect(Str::snake($input))->toBe('camel_case_string');
    expect(Str::kebab($input))->toBe('camel-case-string');
    expect(Str::title($input))->toBe('Camel Case String');
})->with([
    'camel' => ['camelCaseString'],
    'pascal' => ['CamelCaseString'],
    'snake' => ['camel_case_string'],
    'kebab' => ['camel-case-string'],
    'spaced' => ['camel case string'],
]);

it('splits on a delimiter', function () {
    expect(Str::split('a b c'))->toBe(['a', 'b', 'c']);
    expect(Str::split('a,b', ','))->toBe(['a', 'b']);
});

it('returns the text unchanged when it is within the limit', function () {
    expect(Str::limit('short', 10))->toBe('short');
    expect(Str::limit('exact', 5))->toBe('exact');
});

it('truncates and marks the cut', function () {
    expect(Str::limit('abcdefghij', 5))->toBe('ab...');
    expect(Str::limit('abcdefghij', 5, fit: false))->toBe('abcde...');
});

it('truncates from the left when aligned left', function () {
    expect(Str::limit('abcdefghij', 5, align: STR_PAD_LEFT))->toBe('...ij');
    expect(Str::limit('abcdefghij', 5, align: STR_PAD_LEFT, fit: false))->toBe('...fghij');
});
