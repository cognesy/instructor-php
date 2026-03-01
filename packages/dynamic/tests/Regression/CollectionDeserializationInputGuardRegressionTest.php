<?php declare(strict_types=1);

use Cognesy\Dynamic\Field;
use Cognesy\Dynamic\Structure;

it('throws clear exception for non-iterable collection input', function () {
    $structure = Structure::define('Example', [
        Field::collection('items', 'string'),
    ]);

    expect(fn() => $structure->fromArray(['items' => 'abc']))
        ->toThrow(Exception::class, 'expects iterable input');
});

it('keeps valid collection deserialization behavior for iterable input', function () {
    $structure = Structure::define('Example', [
        Field::collection('items', 'string'),
    ]);

    $structure->fromArray(['items' => ['a', 'b', 'c']]);

    expect($structure->items)->toBe(['a', 'b', 'c']);
});
