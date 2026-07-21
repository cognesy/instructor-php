<?php declare(strict_types=1);

use Cognesy\Http\Extras\Support\EventSource\EventSourceStream;
use Cognesy\Http\Stream\ArrayStream;

it('emits an unterminated final SSE event once', function () {
    $stream = new EventSourceStream(
        source: new ArrayStream(["data: first\n\n", 'data: final']),
        request: null,
        response: null,
        listeners: [],
        parser: static fn(string $payload): true => true,
    );

    expect(iterator_to_array($stream, false))->toBe(['first', 'final'])
        ->and($stream->isCompleted())->toBeTrue();
});

it('does not emit a parser value for an empty final buffer', function () {
    $stream = new EventSourceStream(
        source: new ArrayStream(["data: only\n\n"]),
        request: null,
        response: null,
        listeners: [],
        parser: static fn(string $payload): true => true,
    );

    expect(iterator_to_array($stream, false))->toBe(['only']);
});

it('fails clearly when an unterminated SSE buffer exceeds its limit', function () {
    $stream = new EventSourceStream(
        source: new ArrayStream(['data: too-long']),
        request: null,
        response: null,
        listeners: [],
        parser: static fn(string $payload): true => true,
        maxBufferBytes: 8,
    );

    expect(fn() => iterator_to_array($stream, false))
        ->toThrow(LengthException::class, 'SSE parser buffer exceeded')
        ->and($stream->isCompleted())->toBeFalse();
});

it('preserves raw chunks while notifying final parsed payloads without a parser', function () {
    $stream = new EventSourceStream(
        source: new ArrayStream(['data: final']),
        request: null,
        response: null,
        listeners: [],
    );

    expect(iterator_to_array($stream))->toBe(['data: final']);
});
