<?php declare(strict_types=1);

use Cognesy\Utils\Arrays;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\JsonSchemaType;

/**
 * valuesMatch() has a single production caller - JsonSchemaType::isAny(), which
 * compares against the duplicate-free JSON_ANY_TYPES constant. These tests pin both
 * the contract the caller relies on and the documented edge behaviour with
 * duplicates, so a later "cleanup" cannot silently change either one.
 */

it('matches arrays holding the same values in any order', function () {
    expect(Arrays::valuesMatch(['a', 'b'], ['b', 'a']))->toBeTrue();
    expect(Arrays::valuesMatch([], []))->toBeTrue();
    expect(Arrays::valuesMatch(['a'], ['a']))->toBeTrue();
});

it('rejects arrays of different length', function () {
    expect(Arrays::valuesMatch(['a'], ['a', 'b']))->toBeFalse();
    expect(Arrays::valuesMatch(['a', 'b'], ['a']))->toBeFalse();
    expect(Arrays::valuesMatch([], ['a']))->toBeFalse();
});

it('rejects arrays of equal length holding different values', function () {
    expect(Arrays::valuesMatch(['a', 'b'], ['a', 'c']))->toBeFalse();
    expect(Arrays::valuesMatch(['a', 'b'], ['c', 'd']))->toBeFalse();
});

it('ignores the keys of both arrays', function () {
    expect(Arrays::valuesMatch(['x' => 'a', 'y' => 'b'], [0 => 'b', 1 => 'a']))->toBeTrue();
});

it('is set equality whenever one side is duplicate-free', function () {
    // The property the isAny() call site depends on: JSON_ANY_TYPES has no duplicates,
    // so a duplicated type on the other side always changes the count and fails.
    $types = JsonSchemaType::JSON_ANY_TYPES;
    expect(array_unique($types))->toHaveCount(count($types));
    expect(Arrays::valuesMatch($types, array_reverse($types)))->toBeTrue();

    $withDuplicate = $types;
    array_pop($withDuplicate);
    $withDuplicate[] = $types[0];
    expect(Arrays::valuesMatch($types, $withDuplicate))->toBeFalse();
});

it('is neither set nor multiset equality when both sides hold duplicates', function () {
    // Documented, deliberately unchanged: equal counts plus mutual containment.
    expect(Arrays::valuesMatch([1, 1, 2], [1, 2, 2]))->toBeTrue();
    // ...and equal as sets, yet rejected on length.
    expect(Arrays::valuesMatch([1, 1, 2], [1, 2]))->toBeFalse();
});

it('drives isAny() for the full any-type list in any order', function () {
    $schema = JsonSchema::fromArray(['type' => array_reverse(JsonSchemaType::JSON_ANY_TYPES)]);
    expect($schema->isAny())->toBeTrue();
});

it('does not report a narrower type list as any', function () {
    expect(JsonSchema::fromArray(['type' => 'string'])->isAny())->toBeFalse();
    expect(JsonSchema::fromArray(['type' => ['string', 'integer']])->isAny())->toBeFalse();
});
