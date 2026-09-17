<?php

declare(strict_types=1);

use Cognesy\Polyglot\Decision\Data\JsonContent;

it('preserves text object and list identity', function () {
    $text = JsonContent::text('Zażółć gęślą jaźń');
    $object = JsonContent::object([]);
    $list = JsonContent::list([]);

    expect($text->isText())->toBeTrue()
        ->and($text->value())->toBe('Zażółć gęślą jaźń')
        ->and($object->isObject())->toBeTrue()
        ->and($object->value())->toBeInstanceOf(stdClass::class)
        ->and($object->json())->toBe('{}')
        ->and($list->isList())->toBeTrue()
        ->and($list->value())->toBe([])
        ->and($list->json())->toBe('[]');
});

it('preserves numeric-looking object keys', function () {
    $content = JsonContent::object([
        '0' => 'first',
        '1' => 'second',
    ]);
    $value = $content->value();

    expect($value)->toBeInstanceOf(stdClass::class)
        ->and(get_object_vars($value))->toBe(['0' => 'first', '1' => 'second'])
        ->and($content->json())->toBe('{"0":"first","1":"second"}');
});

it('snapshots mutable object input and returned values', function () {
    $source = (object) ['nested' => (object) ['value' => 'original']];
    $content = JsonContent::object($source);
    $source->nested->value = 'mutated';
    $first = $content->value();
    $first->nested->value = 'returned value mutated';
    $second = $content->value();

    expect($second->nested->value)->toBe('original');
});

it('decodes JSON without collapsing object and list identity', function () {
    $content = JsonContent::fromJson('{"elements":{"1":{"label":"Submit"}},"items":[]}');
    $value = $content->value();

    expect($value)->toBeInstanceOf(stdClass::class)
        ->and($value->elements)->toBeInstanceOf(stdClass::class)
        ->and(get_object_vars($value->elements))->toHaveKey('1')
        ->and($value->items)->toBe([]);
});

it('rejects unsupported roots values and nonfinite numbers', function () {
    expect(fn () => JsonContent::fromJson('true'))
        ->toThrow(InvalidArgumentException::class, 'Top-level JSON content')
        ->and(fn () => JsonContent::object(['value' => INF]))
        ->toThrow(InvalidArgumentException::class, 'must be finite')
        ->and(fn () => JsonContent::object(['value' => new DateTimeImmutable]))
        ->toThrow(InvalidArgumentException::class, 'Unsupported JSON content');
});
