<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\DispatchesHttpDriverEvents;
use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Exceptions\HttpRequestException;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * H1 (research/v2-cleanup-plan/03): one payload shape per driver event across
 * all HTTP drivers. Pins the unified key sets.
 */

class DriverEventsHarness
{
    use DispatchesHttpDriverEvents;

    public function __construct(protected EventDispatcherInterface $events) {}

    public function sent(HttpRequest $r): void { $this->dispatchRequestSent($r); }
    public function statusFailed(int $c, HttpRequest $r): void { $this->dispatchStatusCodeFailed($c, $r); }
    public function failed(HttpRequestException $e, HttpRequest $r): void { $this->dispatchRequestFailed($e, $r); }
    public function received(HttpRequest $r, int $c, bool $s, ?string $b): void { $this->dispatchResponseReceived($r, $c, $s, $b); }
}

function driverEventsRequest(): HttpRequest {
    return new HttpRequest('https://api.example.com/v1/x', 'POST', ['Accept' => 'application/json'], '{"q":1}', []);
}

function driverEventsHarness(array &$recorded): DriverEventsHarness {
    $events = new EventDispatcher();
    $events->wiretap(static function (object $e) use (&$recorded): void { $recorded[] = $e; });
    return new DriverEventsHarness($events);
}

it('dispatches request-sent with the unified payload keys', function () {
    $recorded = [];
    driverEventsHarness($recorded)->sent(driverEventsRequest());

    expect($recorded[0])->toBeInstanceOf(HttpRequestSent::class);
    expect(array_intersect(['requestId', 'url', 'method', 'headers', 'requestBodyBytes'], array_keys($recorded[0]->data)))
        ->toBe(['requestId', 'url', 'method', 'headers', 'requestBodyBytes']);
    expect($recorded[0]->data)->not->toHaveKey('body');
});

it('dispatches status-code failure with statusCode key', function () {
    $recorded = [];
    driverEventsHarness($recorded)->statusFailed(500, driverEventsRequest());

    expect($recorded[0])->toBeInstanceOf(HttpRequestFailed::class);
    expect($recorded[0]->data['statusCode'])->toBe(500);
});

it('dispatches request failure with errors plus safe request context', function () {
    $recorded = [];
    $request = driverEventsRequest();
    driverEventsHarness($recorded)->failed(new HttpRequestException('boom', $request), $request);

    expect($recorded[0])->toBeInstanceOf(HttpRequestFailed::class);
    expect($recorded[0]->data['errors'])->toContain('boom');
    expect($recorded[0]->data)->toHaveKeys(['url', 'method', 'headers', 'requestBodyBytes']);
    expect($recorded[0]->data)->not->toHaveKey('body');
});

it('includes body for buffered responses and omits it for streamed ones', function () {
    $recorded = [];
    $harness = driverEventsHarness($recorded);
    $request = driverEventsRequest();

    $harness->received($request, 200, false, '{"ok":true}');
    $harness->received($request, 200, true, null);

    expect($recorded[0])->toBeInstanceOf(HttpResponseReceived::class);
    expect($recorded[0]->data['responseBodyBytes'])->toBe(11);
    expect($recorded[0]->data)->not->toHaveKey('body');
    expect($recorded[1]->data)->not->toHaveKey('body');
    expect($recorded[1]->data['isStreamed'])->toBeTrue();
});

it('redacts sensitive request headers and URL credentials in dispatched events', function () {
    $recorded = [];
    $request = new HttpRequest(
        'https://api.example.com?key=url-secret',
        'POST',
        [
            'Authorization' => 'Bearer header-secret',
            'x-GoOg-aPi-kEy' => 'google-secret',
            'X-Custom' => 'safe',
        ],
        '{"password":"body-secret"}',
        [],
    );

    driverEventsHarness($recorded)->sent($request);

    expect($recorded[0]->data['url'])->not->toContain('url-secret')
        ->and($recorded[0]->data['headers']['Authorization'])->toBe('[REDACTED]')
        ->and($recorded[0]->data['headers']['x-GoOg-aPi-kEy'])->toBe('[REDACTED]')
        ->and($recorded[0]->data['headers']['X-Custom'])->toBe('safe')
        ->and($recorded[0]->data)->not->toHaveKey('body')
        ->and($recorded[0]->data['requestBodyBytes'])->toBe(26);
});
