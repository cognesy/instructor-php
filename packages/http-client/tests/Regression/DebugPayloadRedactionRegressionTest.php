<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\DebugConfig;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Events\DebugRequestBodyUsed;
use Cognesy\Http\Events\DebugRequestHeadersUsed;
use Cognesy\Http\Events\DebugRequestURLUsed;
use Cognesy\Http\Events\DebugResponseBodyReceived;
use Cognesy\Http\Events\DebugResponseHeadersReceived;
use Cognesy\Http\Events\DebugStreamChunkReceived;
use Cognesy\Http\Extras\Support\EventSource\Listeners\DispatchDebugEvents;
use Cognesy\Http\Extras\Support\EventSource\Listeners\PrintToConsole;

function debugSensitiveRequest(): HttpRequest {
    return new HttpRequest(
        'https://example.test?key=url-secret',
        'POST',
        ['Authorization' => 'Bearer header-secret', 'Accept' => 'application/json'],
        '{"token":"body-secret"}',
        [],
    );
}

it('redacts and bounds opt-in debug events', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(static function (object $event) use (&$captured): void {
        $captured[] = $event;
    });
    $listener = new DispatchDebugEvents(
        new DebugConfig(httpBodyMaxBytes: 12),
        $events,
    );
    $request = debugSensitiveRequest();

    $listener->onRequestReceived($request);
    $listener->onStreamChunkReceived(
        $request,
        HttpResponse::streaming(200, [], new \Cognesy\Http\Stream\ArrayStream([])),
        '{"token":"stream-secret"}',
    );
    $listener->onResponseReceived(
        $request,
        HttpResponse::sync(200, ['Set-Cookie' => 'cookie-secret'], '{"token":"response-secret"}'),
    );

    $payload = json_encode(array_map(static fn(object $event): mixed => $event->data, $captured), JSON_THROW_ON_ERROR);

    expect($payload)->not->toContain('url-secret')
        ->and($payload)->not->toContain('header-secret')
        ->and($payload)->not->toContain('body-secret')
        ->and($payload)->not->toContain('stream-secret')
        ->and($payload)->not->toContain('response-secret')
        ->and($payload)->not->toContain('cookie-secret')
        ->and($captured)->toHaveCount(6)
        ->and(array_filter($captured, static fn(object $event): bool => $event instanceof DebugRequestURLUsed))->toHaveCount(1)
        ->and(array_filter($captured, static fn(object $event): bool => $event instanceof DebugRequestHeadersUsed))->toHaveCount(1)
        ->and(array_filter($captured, static fn(object $event): bool => $event instanceof DebugRequestBodyUsed))->toHaveCount(1)
        ->and(array_filter($captured, static fn(object $event): bool => $event instanceof DebugResponseHeadersReceived))->toHaveCount(1)
        ->and(array_filter($captured, static fn(object $event): bool => $event instanceof DebugResponseBodyReceived))->toHaveCount(1)
        ->and(array_filter($captured, static fn(object $event): bool => $event instanceof DebugStreamChunkReceived))->toHaveCount(1);
});

it('redacts bodies and headers printed by the debug console listener', function () {
    $listener = new PrintToConsole(new DebugConfig(httpBodyMaxBytes: 128));
    $request = debugSensitiveRequest();

    ob_start();
    $listener->onRequestReceived($request);
    $listener->onResponseReceived(
        $request,
        HttpResponse::sync(200, ['Set-Cookie' => 'cookie-secret'], '{"token":"response-secret"}'),
    );
    $output = ob_get_clean();

    expect($output)->not->toContain('url-secret')
        ->and($output)->not->toContain('header-secret')
        ->and($output)->not->toContain('body-secret')
        ->and($output)->not->toContain('response-secret')
        ->and($output)->not->toContain('cookie-secret')
        ->and($output)->toContain('[REDACTED]');
});
