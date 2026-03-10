<?php declare(strict_types=1);

namespace Cognesy\Http\Tests\Integration;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Guzzle\GuzzleDriver;
use Cognesy\Http\Drivers\Symfony\SymfonyDriver;
use Cognesy\Http\Tests\Support\IntegrationTestServer;

beforeEach(function () {
    $this->baseUrl = IntegrationTestServer::start();
    $this->events = new EventDispatcher();
    $this->config = new HttpClientConfig(
        connectTimeout: 3,
        requestTimeout: 30,
        failOnError: false,
    );
});

dataset('syncHttpDrivers', [
    'guzzle' => fn(HttpClientConfig $config, EventDispatcher $events): CanHandleHttpRequest => new GuzzleDriver($config, $events),
    'symfony' => fn(HttpClientConfig $config, EventDispatcher $events): CanHandleHttpRequest => new SymfonyDriver($config, $events),
]);

it('preserves plain-text request body in non-curl sync drivers', function (callable $makeDriver) {
    $driver = $makeDriver($this->config, $this->events);

    $request = new HttpRequest(
        url: $this->baseUrl . '/post',
        method: 'POST',
        headers: ['Content-Type' => 'text/plain'],
        body: 'plain-text-body',
        options: [],
    );

    $response = $driver->handle($request);

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('"data": "plain-text-body"');
})->with('syncHttpDrivers');

it('preserves valid json string request body byte-for-byte in non-curl sync drivers', function (callable $makeDriver) {
    $driver = $makeDriver($this->config, $this->events);

    $body = "{\"b\":2, \"a\":1}";

    $request = new HttpRequest(
        url: $this->baseUrl . '/post',
        method: 'POST',
        headers: ['Content-Type' => 'application/json'],
        body: $body,
        options: [],
    );

    $response = $driver->handle($request);

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('"data": "{\\"b\\":2, \\"a\\":1}"');
})->with('syncHttpDrivers');

register_shutdown_function(function () {
    IntegrationTestServer::stop();
});
