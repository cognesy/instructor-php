<?php declare(strict_types=1);

use Cognesy\Http\Stream\IterableStream;

it('is one-shot and throws on replay', function () {
    $stream = new IterableStream((function () {
        yield 'a';
        yield 'b';
    })());

    expect(iterator_to_array($stream))->toBe(['a', 'b']);
    expect(fn() => iterator_to_array($stream))
        ->toThrow(\LogicException::class, 'cannot be replayed');
});
