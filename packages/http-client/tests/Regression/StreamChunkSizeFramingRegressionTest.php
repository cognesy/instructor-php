<?php declare(strict_types=1);

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Drivers\Curl\StreamingCurlResponseAdapter;
use Cognesy\Http\Drivers\Guzzle\PsrHttpResponseAdapter;
use Cognesy\Events\Dispatchers\EventDispatcher;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;

/**
 * streamChunkSize is an upper bound on a streamed chunk, not a target.
 *
 * It used to be 256, which re-sliced every ~8 KB curl write into 32 pieces that
 * EventSourceStream immediately reassembled — 822 substr() copies per 205 KB SSE
 * response for byte-identical parse output.
 *
 * And 0, which presets/symfony.yaml uses to mean "do not split", meant the opposite:
 * splitChunk() did max(1, 0) and produced ONE-BYTE fragments, i.e. 8x more pieces than
 * the 256 default rather than none.
 */

/** Reach splitChunk() without standing up curl handles. */
function splitWith(int $chunkSize, string $chunk): array {
    $method = new ReflectionMethod(StreamingCurlResponseAdapter::class, 'splitChunk');
    $adapter = (new ReflectionClass(StreamingCurlResponseAdapter::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty(StreamingCurlResponseAdapter::class, 'chunkSize'))->setValue($adapter, $chunkSize);

    return iterator_to_array($method->invoke($adapter, $chunk), false);
}

it('defaults to the transport frame size rather than 256 bytes', function () {
    expect(HttpClientConfig::DEFAULT_STREAM_CHUNK_SIZE)->toBe(16384)
        ->and((new HttpClientConfig())->streamChunkSize)->toBe(16384);
});

it('treats a chunk size of zero as no upper bound, not as one byte', function () {
    expect(splitWith(0, 'abcdefg'))->toBe(['abcdefg'])
        ->and(splitWith(-1, 'abcdefg'))->toBe(['abcdefg']);
});

it('still splits when an explicit chunk size is smaller than the chunk', function () {
    expect(splitWith(3, 'abcdefg'))->toBe(['abc', 'def', 'g']);
});

it('passes a chunk through unchanged when it already fits', function () {
    $write = str_repeat('x', 8192);

    expect(splitWith(HttpClientConfig::DEFAULT_STREAM_CHUNK_SIZE, $write))->toBe([$write])
        ->and(splitWith(8192, $write))->toBe([$write]);
});

it('yields nothing for an empty chunk, as the previous loop did', function () {
    expect(splitWith(256, ''))->toBe([])
        ->and(splitWith(0, ''))->toBe([]);
});

it('preserves every byte regardless of framing', function () {
    $body = random_bytes(20_000);

    foreach ([0, 1, 7, 256, 8192, 16384, 32768] as $size) {
        expect(implode('', splitWith($size, $body)))->toBe($body);
    }
});

it('reads PSR streams in blocks of the configured size', function () {
    $body = str_repeat('y', 40_000);
    $stream = Utils::streamFor($body);

    $adapter = new PsrHttpResponseAdapter(
        response: new Response(200, [], $stream),
        stream: $stream,
        events: new EventDispatcher(),
        isStreamed: true,
        requestId: 'req-framing',
    );

    $chunks = iterator_to_array($adapter->toHttpResponse()->stream(), false);

    // 40,000 bytes at 16,384 -> 16384 + 16384 + 7232. The final read is short, which
    // sets the underlying EOF flag, so no extra empty read is needed here. A body that
    // is an exact multiple of the block size does get a trailing empty chunk — see
    // the next case and instructor-f7g5.
    expect(array_map('strlen', $chunks))->toBe([16384, 16384, 7232])
        ->and(implode('', $chunks))->toBe($body);
});

it('emits a trailing empty chunk only when the body is an exact multiple of the block', function () {
    $body = str_repeat('z', 32);
    $stream = Utils::streamFor($body);

    $adapter = new PsrHttpResponseAdapter(
        response: new Response(200, [], $stream),
        stream: $stream,
        events: new EventDispatcher(),
        isStreamed: true,
        requestId: 'req-exact',
        streamChunkSize: 16,
    );

    // Neither read is short, so eof() stays false until a third read returns ''.
    expect(array_map('strlen', iterator_to_array($adapter->toHttpResponse()->stream(), false)))
        ->toBe([16, 16, 0]);
});

it('terminates on a body shorter than one block', function () {
    $stream = Utils::streamFor('short');

    $adapter = new PsrHttpResponseAdapter(
        response: new Response(200, [], $stream),
        stream: $stream,
        events: new EventDispatcher(),
        isStreamed: true,
        requestId: 'req-short',
    );

    expect(implode('', iterator_to_array($adapter->toHttpResponse()->stream(), false)))->toBe('short');
});
