<?php declare(strict_types=1);

use Cognesy\Http\Stream\BufferedStream;

it('does not mark buffered stream as completed after partial iteration and rejects replay', function () {
    $stream = BufferedStream::fromStream((function (): Generator {
        yield 'chunk-1';
        yield 'chunk-2';
        yield 'chunk-3';
    })());

    foreach ($stream as $chunk) {
        expect($chunk)->toBe('chunk-1');
        break;
    }

    expect($stream->isCompleted())->toBeFalse()
        ->and(fn() => iterator_to_array($stream))
        ->toThrow(LogicException::class);
});
