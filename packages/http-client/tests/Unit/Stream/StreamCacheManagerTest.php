<?php declare(strict_types=1);

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Enums\StreamCachePolicy;
use Cognesy\Http\Stream\ArrayStream;
use Cognesy\Http\Stream\IterableStream;
use Cognesy\Http\Stream\StreamCacheManager;

it('uses one-shot stream wrapper for none policy', function () {
    $response = HttpResponse::streaming(
        statusCode: 200,
        headers: [],
        stream: new ArrayStream(['x', 'y']),
    );

    $managed = (new StreamCacheManager())->manage($response, StreamCachePolicy::None);

    expect(iterator_to_array($managed->stream()))->toBe(['x', 'y']);
    expect(fn() => iterator_to_array($managed->stream()))
        ->toThrow(\LogicException::class, 'cannot be replayed');
});

it('uses replayable memory wrapper for memory policy', function () {
    $response = HttpResponse::streaming(
        statusCode: 200,
        headers: [],
        stream: new IterableStream((function () {
            yield 'x';
            yield 'y';
        })()),
    );

    $managed = (new StreamCacheManager())->manage($response, StreamCachePolicy::Memory);

    expect(iterator_to_array($managed->stream()))->toBe(['x', 'y']);
    expect(iterator_to_array($managed->stream()))->toBe(['x', 'y']);
});
