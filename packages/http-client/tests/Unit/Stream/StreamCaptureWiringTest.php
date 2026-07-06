<?php declare(strict_types=1);

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Stream\ArrayStream;
use Cognesy\Http\Stream\CapturingStream;
use Cognesy\Http\Stream\StreamCapturingPolicy;

/**
 * H2 (research/v2-cleanup-plan/03): CapturingStream is reachable via
 * HttpResponse::withStreamCapture() — opt-in, per-response, no config change.
 */

it('captures consumed chunks when capture is enabled on a response', function () {
    $response = HttpResponse::streaming(200, [], ArrayStream::from(['{"a":', '1}']))
        ->withStreamCapture(StreamCapturingPolicy::full(maxBytes: 1024));

    $chunks = iterator_to_array($response->stream());

    expect($chunks)->toBe(['{"a":', '1}']);
    $capture = $response->streamCapture();
    expect($capture)->toBeInstanceOf(CapturingStream::class);
    expect($capture->capturedBody())->toBe('{"a":1}');
    expect($capture->stats()->chunks)->toBe(2);
});

it('reports no capture on responses without opt-in', function () {
    $response = HttpResponse::streaming(200, [], ArrayStream::from(['x']));

    expect($response->streamCapture())->toBeNull();
});

it('passes chunks through unchanged when the policy is disabled', function () {
    $response = HttpResponse::streaming(200, [], ArrayStream::from(['x', 'y']))
        ->withStreamCapture(StreamCapturingPolicy::disabled());

    expect(iterator_to_array($response->stream()))->toBe(['x', 'y']);
    expect($response->streamCapture()->capturedBody())->toBe('');
});
