<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Streaming\EventStreamReader;

/**
 * readSseEvents() maintains the implicit-boundary facts incrementally instead of rescanning
 * the buffered lines for each incoming line. These pin the parsing shapes that heuristic is
 * responsible for, plus the scaling behaviour that motivated the change.
 */

function sseReader(): EventStreamReader {
    return new EventStreamReader(
        events: new EventDispatcher(),
        parser: fn(string $line): string|bool => $line === 'data: [DONE]' ? false : substr($line, 6),
    );
}

/** @return list<string> */
function sseRead(string $body): array {
    return iterator_to_array(sseReader()->eventsFrom([$body]), false);
}

it('splits standalone data lines from a relay that omits blank separators', function () {
    expect(sseRead("data: {\"a\":1}\ndata: {\"a\":2}\ndata: [DONE]\n"))
        ->toEqual(['{"a":1}', '{"a":2}']);
});

it('splits on an event line once the buffer already holds a data line', function () {
    expect(sseRead("event: msg\ndata: {\"a\":1}\nevent: msg\ndata: {\"a\":2}\n"))
        ->toEqual(['{"a":1}', '{"a":2}']);
});

it('keeps a multi-line data payload together when blank lines delimit it', function () {
    expect(sseRead("data: {\ndata: \"a\":1}\n\ndata: {\ndata: \"a\":2}\n\n"))
        ->toEqual(["{\n\"a\":1}", "{\n\"a\":2}"]);
});

it('does not split a multi-line data payload that has no blank separators', function () {
    // Continuation lines are not standalone, so no implicit boundary applies and the whole
    // run stays one event.
    expect(sseRead("data: {\ndata: \"a\":1}\ndata: {\ndata: \"a\":2}\n"))
        ->toEqual(["{\n\"a\":1}\n{\n\"a\":2}"]);
});

it('ignores comment lines when deciding a boundary', function () {
    expect(sseRead(": ping\ndata: {\"a\":1}\n\n: ping\ndata: {\"a\":2}\n\n"))
        ->toEqual(['{"a":1}', '{"a":2}']);
});

it('handles id and event fields preceding data', function () {
    expect(sseRead("id: 1\nevent: msg\ndata: {\"a\":1}\n\nid: 2\nevent: msg\ndata: {\"a\":2}\n\n"))
        ->toEqual(['{"a":1}', '{"a":2}']);
});

it('yields nothing for a stream of event lines with no data', function () {
    expect(sseRead("event: ping\nevent: ping\nevent: ping\n"))->toEqual([]);
});

it('scales linearly on event-only input with no blank separators', function () {
    // This shape used to rescan the whole buffer per line: the larger input grew far faster
    // than the smaller one. Use a larger scale gap and a noise floor so this remains a
    // complexity regression test rather than a machine-speed benchmark.
    $timeFor = function (int $n): float {
        $body = str_repeat("event: ping\n", $n);
        $start = hrtime(true);
        sseRead($body);
        return (hrtime(true) - $start) / 1e6;
    };

    $timeFor(500); // warm up autoloading and the JIT-free opcode path

    $small = $timeFor(1000);
    $large = $timeFor(8000);

    // Linear would be ~8x, while the previous quadratic implementation grew by ~64x for
    // this input-size ratio. The 16x ceiling leaves room for CI noise but still catches the
    // rescan when it returns.
    expect($large)->toBeLessThan(max($small, 2.0) * 16);
});
