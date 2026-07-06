<?php declare(strict_types=1);

use Cognesy\Http\Stream\ArrayStream;
use Cognesy\Http\Stream\CapturingStream;
use Cognesy\Http\Stream\StreamCapturingPolicy;

it('forwards chunks and records stats when capture is disabled', function () {
    $stream = new CapturingStream(
        inner: ArrayStream::from(['abc', 'de', 'fghi']),
        policy: StreamCapturingPolicy::disabled(),
    );

    $chunks = iterator_to_array($stream);
    $stats = $stream->stats();

    expect($chunks)->toBe(['abc', 'de', 'fghi']);
    expect($stream->isCompleted())->toBeTrue();
    expect($stats->bytes)->toBe(9);
    expect($stats->chunks)->toBe(3);
    expect($stats->capturedBytes)->toBe(0);
    expect($stats->truncated)->toBeFalse();
    expect($stream->preview())->toBe('');
    expect($stream->capturedBody())->toBe('');
    expect($stream->capturedChunks())->toBe([]);
});

it('captures a bounded preview without retaining chunks', function () {
    $stream = new CapturingStream(
        inner: ArrayStream::from(['abc', 'def', 'ghi']),
        policy: StreamCapturingPolicy::preview(maxBytes: 5),
    );

    expect(iterator_to_array($stream))->toBe(['abc', 'def', 'ghi']);

    $stats = $stream->stats();

    expect($stream->preview())->toBe('abcde');
    expect($stream->capturedBody())->toBe('abcde');
    expect($stream->capturedChunks())->toBe([]);
    expect($stats->bytes)->toBe(9);
    expect($stats->chunks)->toBe(3);
    expect($stats->capturedBytes)->toBe(5);
    expect($stats->truncated)->toBeTrue();
});

it('captures bounded chunks and truncates the final captured chunk', function () {
    $stream = new CapturingStream(
        inner: ArrayStream::from(['abc', 'def', 'ghi']),
        policy: StreamCapturingPolicy::chunks(maxBytes: 5),
    );

    expect(iterator_to_array($stream))->toBe(['abc', 'def', 'ghi']);

    $stats = $stream->stats();

    expect($stream->capturedChunks())->toBe(['abc', 'de']);
    expect($stream->capturedBody())->toBe('abcde');
    expect($stats->bytes)->toBe(9);
    expect($stats->chunks)->toBe(3);
    expect($stats->capturedBytes)->toBe(5);
    expect($stats->truncated)->toBeTrue();
});

it('stays single pass and keeps partial stats after interruption', function () {
    $stream = new CapturingStream(
        inner: ArrayStream::from(['abc', 'def']),
        policy: StreamCapturingPolicy::full(maxBytes: 100),
    );

    foreach ($stream as $chunk) {
        expect($chunk)->toBe('abc');
        break;
    }

    $stats = $stream->stats();

    expect($stream->isCompleted())->toBeFalse();
    expect($stream->capturedBody())->toBe('abc');
    expect($stats->bytes)->toBe(3);
    expect($stats->chunks)->toBe(1);
    expect(fn() => iterator_to_array($stream))->toThrow(LogicException::class);
});
