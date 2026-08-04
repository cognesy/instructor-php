<?php declare(strict_types=1);

use Cognesy\Http\Extras\Support\EventSource\EventSourceStream;
use Cognesy\Http\Stream\ArrayStream;

/**
 * The parser used to drop each consumed event with substr($buffer, $pos + 2),
 * reallocating everything still unparsed once per event. It now tracks a consumed
 * prefix and compacts in bulk, and takes a fast path for the single-"data: " block
 * that essentially every provider event is.
 *
 * These cases pin the WHATWG behaviours that the offset arithmetic and the fast path
 * could plausibly break.
 */

/**
 * @param list<string> $chunks
 * @return list<string>
 */
function sseEvents(array $chunks, int $maxBufferBytes = 1_048_576): array {
    $stream = new EventSourceStream(
        source: new ArrayStream($chunks),
        request: null,
        response: null,
        listeners: [],
        parser: static fn(string $payload): true => true,
        maxBufferBytes: $maxBufferBytes,
    );

    return iterator_to_array($stream, false);
}

it('joins multiple data lines with a newline rather than taking the fast path', function () {
    expect(sseEvents(["data: one\ndata: two\ndata: three\n\n"]))->toBe(["one\ntwo\nthree"]);
});

it('ignores comment lines', function () {
    expect(sseEvents([": keepalive\ndata: real\n\n"]))->toBe(['real']);
});

it('drops a block that is only a comment', function () {
    expect(sseEvents([": keepalive\n\ndata: real\n\n"]))->toBe(['real']);
});

it('ignores fields other than data', function () {
    expect(sseEvents(["event: message\nid: 42\nretry: 1000\ndata: payload\n\n"]))->toBe(['payload']);
});

it('keeps a data value that has no leading space', function () {
    expect(sseEvents(["data:tight\n\n"]))->toBe(['tight']);
});

it('preserves an empty data value by dropping the block', function () {
    expect(sseEvents(["data: \n\ndata: kept\n\n"]))->toBe(['kept']);
});

it('normalises CRLF and bare CR line endings', function () {
    expect(sseEvents(["data: crlf\r\n\r\n"]))->toBe(['crlf'])
        ->and(sseEvents(["data: cr\r\r"]))->toBe(['cr'])
        ->and(sseEvents(["data: a\r\ndata: b\r\n\r\n"]))->toBe(["a\nb"]);
});

it('reassembles an event split across chunk boundaries', function () {
    expect(sseEvents(['data: sp', 'lit acr', "oss\n", "\ndata: second\n\n"]))
        ->toBe(['split across', 'second']);
});

it('reassembles when the blank-line terminator itself is split', function () {
    expect(sseEvents(["data: one\n", "\n", "data: two\n", "\n"]))->toBe(['one', 'two']);
});

it('emits an unterminated final block at end of stream', function () {
    expect(sseEvents(["data: first\n\n", 'data: final']))->toBe(['first', 'final']);
});

it('parses a long run of events with correct ordering and no loss', function () {
    $chunks = [];
    $expected = [];
    for ($i = 0; $i < 500; $i++) {
        $chunks[] = "data: event-{$i}\n\n";
        $expected[] = "event-{$i}";
    }

    expect(sseEvents($chunks))->toBe($expected);
});

it('parses correctly when many events arrive in one oversized chunk', function () {
    $body = '';
    $expected = [];
    for ($i = 0; $i < 500; $i++) {
        $body .= "data: bulk-{$i}\n\n";
        $expected[] = "bulk-{$i}";
    }

    expect(sseEvents([$body]))->toBe($expected);
});

it('applies the buffer limit to unparsed bytes, not to everything ever received', function () {
    // 500 complete events, each consumed immediately, against a limit far smaller than
    // their total. Measuring the raw buffer would trip this the moment the consumed
    // prefix outgrew the limit, which is exactly the bug the offset could introduce.
    $chunks = [];
    for ($i = 0; $i < 500; $i++) {
        $chunks[] = "data: event-{$i}\n\n";
    }

    expect(sseEvents($chunks, maxBufferBytes: 64))->toHaveCount(500);
});

it('still fails when a single unterminated block exceeds the limit', function () {
    expect(fn() => sseEvents(['data: far-too-long-to-fit'], maxBufferBytes: 8))
        ->toThrow(LengthException::class);
});

it('fails when an unterminated block grows past the limit across chunks', function () {
    expect(fn() => sseEvents(['data: start', str_repeat('x', 64)], maxBufferBytes: 32))
        ->toThrow(LengthException::class);
});

it('yields raw chunks unchanged when no parser is configured', function () {
    $stream = new EventSourceStream(
        source: new ArrayStream(["data: one\n\n", "data: two\n\n"]),
        request: null,
        response: null,
        listeners: [],
        parser: null,
    );

    expect(iterator_to_array($stream, false))->toBe(["data: one\n\n", "data: two\n\n"]);
});
