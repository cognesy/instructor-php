<?php declare(strict_types=1);

use Cognesy\Utils\Arrays;

/**
 * toBullets() gave every item its own trailing "\n" and then joined the items with
 * "\n" as well, so each list came out double-spaced with a stray newline at the end -
 * contradicting the documented "- item1\n- item2" shape. These lists are pasted
 * straight into prompts, where the blank lines are noise.
 */

it('renders one bullet per line without blank lines between them', function () {
    expect(Arrays::toBullets(['a', 'b', 'c']))->toBe(" - a\n - b\n - c");
});

it('does not leave a trailing newline', function () {
    expect(Arrays::toBullets(['only']))->toBe(' - only');
    expect(Arrays::toBullets(['a', 'b']))->not->toEndWith("\n");
});

it('renders an empty list as an empty string', function () {
    expect(Arrays::toBullets([]))->toBe('');
});

it('produces exactly one line per item', function () {
    $items = ['one', 'two', 'three', 'four'];
    $lines = explode("\n", Arrays::toBullets($items));
    expect($lines)->toHaveCount(count($items));
});

it('stringifies non-string items', function () {
    expect(Arrays::toBullets([1, 2.5, true]))->toBe(" - 1\n - 2.5\n - 1");
});

// isSubset() had no declared return type, so it leaked an untyped value into
// callers relying on a strict bool.

it('declares a bool return type', function () {
    $method = new ReflectionMethod(Arrays::class, 'isSubset');
    expect((string) $method->getReturnType())->toBe('bool');
});

it('detects subset relationships by value', function () {
    expect(Arrays::isSubset(['a'], ['a', 'b']))->toBeTrue();
    expect(Arrays::isSubset(['a', 'b'], ['a', 'b']))->toBeTrue();
    expect(Arrays::isSubset([], ['a']))->toBeTrue();
    expect(Arrays::isSubset(['c'], ['a', 'b']))->toBeFalse();
    expect(Arrays::isSubset(['a', 'c'], ['a', 'b']))->toBeFalse();
});
