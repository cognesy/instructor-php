<?php declare(strict_types=1);

use Cognesy\Utils\Str;

/**
 * endsWith() was implemented as substr($text, -strlen($suffix)) === $suffix.
 * For an empty suffix the offset becomes -0, which is 0, so substr returned the
 * WHOLE string and the comparison against '' failed. startsWith() used a
 * substr($text, 0, 0) that yielded '' and correctly returned true, so the two
 * halves of the same predicate disagreed on the same input.
 */

it('reports that every string ends with an empty suffix', function () {
    expect(Str::endsWith('abc', ''))->toBeTrue();
    expect(Str::endsWith('', ''))->toBeTrue();
});

it('agrees with startsWith on the empty needle', function () {
    foreach (['abc', '', 'a', 'multi word text'] as $text) {
        expect(Str::endsWith($text, ''))
            ->toBe(Str::startsWith($text, ''), "disagreed on '{$text}'");
    }
});

it('matches the native php predicates across representative inputs', function () {
    $cases = [
        ['abc', ''], ['abc', 'c'], ['abc', 'bc'], ['abc', 'abc'],
        ['abc', 'abcd'], ['abc', 'a'], ['', 'a'], ['', ''],
        ['aa', 'aaa'], ['ünïcode', 'code'],
    ];
    foreach ($cases as [$text, $needle]) {
        expect(Str::endsWith($text, $needle))
            ->toBe(str_ends_with($text, $needle), "endsWith('{$text}', '{$needle}')");
        expect(Str::startsWith($text, $needle))
            ->toBe(str_starts_with($text, $needle), "startsWith('{$text}', '{$needle}')");
    }
});

it('still rejects a suffix longer than the subject', function () {
    // The old implementation's substr($text, -4) on a 3-char string returned the
    // whole string, which happened to be right here - keep it pinned.
    expect(Str::endsWith('abc', 'xabc'))->toBeFalse();
});
