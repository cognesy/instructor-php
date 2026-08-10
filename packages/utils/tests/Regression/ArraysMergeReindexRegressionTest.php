<?php declare(strict_types=1);

use Cognesy\Utils\Arrays;

/**
 * mergeMany()/mergeOver() document "semantics match PHP's array_merge: numeric keys
 * are reindexed", but their one-element fast path returned $parts[0] verbatim and so
 * skipped the reindexing. mergeMany([[5 => 'a']]) yielded [5 => 'a'] where
 * array_merge([5 => 'a']) yields [0 => 'a'] - the result shape depended on how many
 * non-empty arrays happened to survive filtering.
 */

it('reindexes numeric keys even when only one array survives', function () {
    expect(Arrays::mergeMany([[5 => 'a']]))->toBe(['a']);
    expect(Arrays::mergeOver([[7 => 'z']], fn($x) => $x))->toBe(['z']);
});

it('agrees with array_merge regardless of how many parts survive', function () {
    $cases = [
        [[5 => 'a']],
        [[5 => 'a'], [9 => 'b']],
        [['k' => 1]],
        [['k' => 1], ['k' => 2]],
        [[0 => 'x'], [0 => 'y']],
    ];
    foreach ($cases as $parts) {
        expect(Arrays::mergeMany($parts))->toBe(array_merge(...$parts));
    }
});

it('drops empty and non-array entries before merging', function () {
    expect(Arrays::mergeMany([[], [5 => 'a'], [], 'not-an-array', null]))->toBe(['a']);
});

it('returns an empty array when nothing survives', function () {
    expect(Arrays::mergeMany([]))->toBe([]);
    expect(Arrays::mergeMany([[], []]))->toBe([]);
    expect(Arrays::mergeOver([], fn($x) => $x))->toBe([]);
    expect(Arrays::mergeOver([1, 2], fn() => []))->toBe([]);
});

it('keeps string keys and lets later values win', function () {
    expect(Arrays::mergeMany([['a' => 1, 'b' => 2], ['b' => 3]]))
        ->toBe(['a' => 1, 'b' => 3]);
});

it('passes both value and key to the mergeOver mapper', function () {
    $seen = [];
    Arrays::mergeOver(['x' => 'v'], function ($item, $key) use (&$seen) {
        $seen[] = [$item, $key];
        return [$item];
    });
    expect($seen)->toBe([['v', 'x']]);
});
