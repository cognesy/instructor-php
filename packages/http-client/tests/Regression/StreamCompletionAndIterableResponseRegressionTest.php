<?php declare(strict_types=1);

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Enums\StreamCachePolicy;
use Cognesy\Http\Stream\ArrayStream;
use Cognesy\Http\Stream\BufferedStream;
use Cognesy\Http\Stream\IterableStream;
use Cognesy\Http\Stream\StreamCacheManager;

it('does not mark ArrayStream complete after an interrupted iteration', function () {
    $stream = new ArrayStream(['a', 'b']);

    foreach ($stream as $chunk) {
        expect($chunk)->toBe('a');
        break;
    }

    expect($stream->isCompleted())->toBeFalse()
        ->and(iterator_to_array($stream))->toBe(['a', 'b'])
        ->and($stream->isCompleted())->toBeTrue();
});

it('does not mark IterableStream complete after an interrupted iteration', function () {
    $stream = new IterableStream((function (): Generator {
        yield 'a';
        yield 'b';
    })());

    foreach ($stream as $chunk) {
        expect($chunk)->toBe('a');
        break;
    }

    expect($stream->isCompleted())->toBeFalse()
        ->and(fn() => iterator_to_array($stream))
        ->toThrow(LogicException::class);
});

it('marks IterableStream complete only after a full drain', function () {
    $stream = new IterableStream(['a', 'b']);

    expect(iterator_to_array($stream))->toBe(['a', 'b'])
        ->and($stream->isCompleted())->toBeTrue()
        ->and(fn() => iterator_to_array($stream))->toThrow(LogicException::class);
});

it('rejects raw iterable response streams and exposes explicit alternatives', function () {
    $source = (function (): Generator {
        yield 'a';
        yield 'b';
    })();

    expect(fn() => new HttpResponse(200, '', [], true, $source))
        ->toThrow(LogicException::class, 'Raw iterables are not accepted');

    $streamed = HttpResponse::streamingFromIterable(200, [], ['a', 'b']);
    expect($streamed->rawStream())->toBeInstanceOf(IterableStream::class)
        ->and(iterator_to_array($streamed->stream()))->toBe(['a', 'b']);

    $buffered = HttpResponse::bufferedFromIterable(200, [], ['a', 'b']);
    expect(iterator_to_array($buffered->stream()))->toBe(['a', 'b'])
        ->and(iterator_to_array($buffered->stream()))->toBe(['a', 'b'])
        ->and($buffered->rawStream())->toBeInstanceOf(BufferedStream::class);
});

it('does not consume an iterable while constructing a non-buffering response', function () {
    $yielded = 0;
    $response = HttpResponse::streamingFromIterable(200, [], (function () use (&$yielded): Generator {
        $yielded++;
        yield 'first';
        $yielded++;
        yield 'second';
    })());

    expect($yielded)->toBe(0);
    foreach ($response->stream() as $chunk) {
        expect($chunk)->toBe('first');
        break;
    }
    expect($yielded)->toBe(1)
        ->and($response->isStreaming())->toBeTrue();
});

it('does not wrap an existing non-buffering iterable stream for the none cache policy', function () {
    $stream = new IterableStream(['a']);
    $response = HttpResponse::streaming(200, [], $stream);
    $managed = (new StreamCacheManager())->manage($response, StreamCachePolicy::None);

    expect($managed->rawStream())->toBe($stream);
});
