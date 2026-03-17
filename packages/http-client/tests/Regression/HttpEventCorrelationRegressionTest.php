<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Curl\CurlDriver;
use Cognesy\Http\Drivers\Guzzle\GuzzleDriver;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Http\Drivers\Symfony\SymfonyDriver;
use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseChunkReceived;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\Tests\Support\IntegrationTestServer;
use GuzzleHttp\Client;
use Symfony\Component\HttpClient\HttpClient;

beforeEach(function () {
    $this->baseUrl = IntegrationTestServer::start();
});

afterEach(function () {
    IntegrationTestServer::stop();
});

function createCorrelationDriver(string $type, HttpClientConfig $config, EventDispatcher $events): object {
    return match ($type) {
        'curl' => new CurlDriver($config, $events),
        'guzzle' => new GuzzleDriver($config, $events, new Client()),
        'symfony' => new SymfonyDriver($config, $events, HttpClient::create()),
        default => throw new InvalidArgumentException("Unknown driver type: {$type}"),
    };
}

it('includes a stable requestId across http request response and chunk events', function (string $driverType) {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(function (object $event) use (&$captured): void {
        $captured[] = $event;
    });

    $driver = createCorrelationDriver(
        type: $driverType,
        config: new HttpClientConfig(failOnError: false),
        events: $events,
    );

    $request = new HttpRequest($this->baseUrl . '/stream/2', 'GET', [], '', ['stream' => true]);
    $response = $driver->handle($request);

    foreach ($response->stream() as $_chunk) {
    }

    $requestSent = collectEvent($captured, HttpRequestSent::class);
    $responseReceived = collectEvent($captured, HttpResponseReceived::class);
    $chunkEvents = collectEvents($captured, HttpResponseChunkReceived::class);

    expect($requestSent->data)->toBeArray()->toHaveKey('requestId', $request->id);
    expect($responseReceived->data)->toBeArray()->toHaveKey('requestId', $request->id);
    expect($responseReceived->data)->toHaveKey('statusCode', 200);
    expect($chunkEvents)->not->toBeEmpty();

    foreach ($chunkEvents as $event) {
        expect($event->data)->toBeArray()->toHaveKey('requestId', $request->id);
        expect($event->data['chunk'])->toBeString();
    }
})->with(['guzzle', 'symfony', 'curl']);

it('includes a stable requestId across http failure events', function (string $driverType) {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(function (object $event) use (&$captured): void {
        $captured[] = $event;
    });

    $driver = createCorrelationDriver(
        type: $driverType,
        config: new HttpClientConfig(failOnError: true),
        events: $events,
    );

    $request = new HttpRequest($this->baseUrl . '/status/404', 'GET', [], '', []);

    try {
        $driver->handle($request);
        throw new RuntimeException('Expected request failure');
    } catch (HttpRequestException) {
    }

    $requestSent = collectEvent($captured, HttpRequestSent::class);
    $requestFailed = collectEvent($captured, HttpRequestFailed::class);

    expect($requestSent->data)->toBeArray()->toHaveKey('requestId', $request->id);
    expect($requestFailed->data)->toBeArray()->toHaveKey('requestId', $request->id);
})->with(['guzzle', 'symfony', 'curl']);

it('mock driver emits normalized response payload with requestId', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(function (object $event) use (&$captured): void {
        $captured[] = $event;
    });

    $driver = new MockHttpDriver($events);
    $request = new HttpRequest('https://example.com/mock', 'GET', [], '', []);

    $driver->addResponse(
        MockHttpResponseFactory::success(body: '{"ok":true}'),
        $request->url(),
        $request->method(),
    );

    $driver->handle($request);

    $responseReceived = collectEvent($captured, HttpResponseReceived::class);

    expect($responseReceived->data)->toBe([
        'requestId' => $request->id,
        'statusCode' => 200,
    ]);

    foreach ($responseReceived->data as $value) {
        expect(is_object($value))->toBeFalse();
    }
});

function collectEvent(array $events, string $class): object {
    foreach ($events as $event) {
        if ($event instanceof $class) {
            return $event;
        }
    }

    throw new RuntimeException("Missing event: {$class}");
}

function collectEvents(array $events, string $class): array {
    return array_values(array_filter(
        $events,
        static fn(object $event): bool => $event instanceof $class,
    ));
}
