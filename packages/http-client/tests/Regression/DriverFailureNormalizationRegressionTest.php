<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Guzzle\GuzzleDriver;
use Cognesy\Http\Drivers\Symfony\SymfonyDriver;
use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Exceptions\ConnectionException;
use Cognesy\Http\Exceptions\TimeoutException;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

it('maps a structurally identified Guzzle timeout to TimeoutException and emits failure', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(static function (object $event) use (&$captured): void {
        $captured[] = $event;
    });
    $request = new HttpRequest('https://example.test/timeout', 'GET', [], '', []);
    $mock = new MockHandler([
        new ConnectException(
            'transfer failed',
            new Request('GET', $request->url()),
            null,
            ['errno' => 28],
        ),
    ]);
    $driver = new GuzzleDriver(
        new HttpClientConfig(),
        $events,
        new Client(['handler' => HandlerStack::create($mock)]),
    );

    expect(fn() => $driver->handle($request))->toThrow(TimeoutException::class);

    $failures = array_values(array_filter(
        $captured,
        static fn(object $event): bool => $event instanceof HttpRequestFailed,
    ));
    expect($failures)->toHaveCount(1)
        ->and($failures[0]->data['requestId'])->toBe($request->id);
});

it('maps a Guzzle connection failure to ConnectionException', function () {
    $request = new HttpRequest('https://example.test/connect', 'GET', [], '', []);
    $mock = new MockHandler([
        new ConnectException(
            'connection failed',
            new Request('GET', $request->url()),
            null,
            ['errno' => 7],
        ),
    ]);
    $driver = new GuzzleDriver(
        new HttpClientConfig(),
        new EventDispatcher(),
        new Client(['handler' => HandlerStack::create($mock)]),
    );

    expect(fn() => $driver->handle($request))->toThrow(ConnectionException::class);
});

it('does not convert programmer exceptions from Guzzle into retriable network errors', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(static function (object $event) use (&$captured): void {
        $captured[] = $event;
    });
    $request = new HttpRequest('https://example.test/programmer-error', 'GET', [], '', []);
    $mock = new MockHandler([new RuntimeException('programmer failure')]);
    $driver = new GuzzleDriver(
        new HttpClientConfig(),
        $events,
        new Client(['handler' => HandlerStack::create($mock)]),
    );

    expect(fn() => $driver->handle($request))
        ->toThrow(RuntimeException::class, 'programmer failure')
        ->and(array_values(array_filter(
            $captured,
            static fn(object $event): bool => $event instanceof HttpRequestFailed,
        )))->toHaveCount(0);
});

it('maps Symfony transport timeout exceptions structurally and emits failure', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(static function (object $event) use (&$captured): void {
        $captured[] = $event;
    });
    $driver = new SymfonyDriver(
        config: new HttpClientConfig(driver: 'symfony', connectTimeout: 1),
        events: $events,
        clientInstance: new MockHttpClient(new MockResponse([''])),
    );

    expect(fn() => $driver->handle(new HttpRequest('https://example.test/timeout', 'GET', [], '', [])))
        ->toThrow(TimeoutException::class);
    expect(array_values(array_filter(
        $captured,
        static fn(object $event): bool => $event instanceof HttpRequestFailed,
    )))->toHaveCount(1);
});

it('does not convert Symfony programmer exceptions into retriable network errors', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(static function (object $event) use (&$captured): void {
        $captured[] = $event;
    });
    $driver = new SymfonyDriver(
        config: new HttpClientConfig(driver: 'symfony'),
        events: $events,
        clientInstance: new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            throw new RuntimeException('programmer failure');
        }),
    );

    expect(fn() => $driver->handle(new HttpRequest('https://example.test/programmer-error', 'GET', [], '', [])))
        ->toThrow(RuntimeException::class, 'programmer failure');
    expect(array_values(array_filter(
        $captured,
        static fn(object $event): bool => $event instanceof HttpRequestFailed,
    )))->toHaveCount(0);
});
