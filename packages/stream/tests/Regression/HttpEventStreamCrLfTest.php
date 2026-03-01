<?php declare(strict_types=1);

use Cognesy\Stream\Sources\Http\HttpEventStream;

/**
 * @param list<string> $chunks
 * @return Generator<string>
 */
function httpEventChunks(array $chunks): Generator
{
    foreach ($chunks as $chunk) {
        yield $chunk;
    }
}

it('emits separate events for CRLF-delimited SSE frames', function () {
    $stream = new HttpEventStream(httpEventChunks([
        "data: one\r\n\r\n",
        "data: two\r\n\r\n",
    ]));

    $events = iterator_to_array($stream->getIterator(), false);

    expect($events)->toBe(['one', 'two']);
});

it('handles CRLF delimiters split across chunk boundaries', function () {
    $stream = new HttpEventStream(httpEventChunks([
        "data: one\r\n\r",
        "\ndata: two\r\n\r",
        "\n",
    ]));

    $events = iterator_to_array($stream->getIterator(), false);

    expect($events)->toBe(['one', 'two']);
});

it('keeps LF-delimited behavior unchanged', function () {
    $stream = new HttpEventStream(httpEventChunks([
        "data: one\n\n",
        "data: two\n\n",
    ]));

    $events = iterator_to_array($stream->getIterator(), false);

    expect($events)->toBe(['one', 'two']);
});
