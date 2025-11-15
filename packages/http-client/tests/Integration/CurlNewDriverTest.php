<?php

declare(strict_types=1);

namespace Cognesy\Http\Tests\Integration;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Curl\CurlDriver;
use Cognesy\Http\Tests\Support\IntegrationTestServer;

beforeEach(function () {
    $this->baseUrl = IntegrationTestServer::start();
    $this->events = new EventDispatcher();
    $this->config = new HttpClientConfig(
        driver: 'curl',
        connectTimeout: 3,
        requestTimeout: 30,
        streamChunkSize: 256,
        failOnError: false,
    );
    $this->driver = new CurlDriver($this->config, $this->events);
});

afterEach(function () {
    // Server cleanup handled by shutdown function
});

it('can be instantiated', function () {
    expect($this->driver)->toBeInstanceOf(CurlDriver::class);
});

it('throws exception when curl extension is not loaded', function () {
    if (!extension_loaded('curl')) {
        expect(fn() => new CurlDriver($this->config, $this->events))
            ->toThrow(\RuntimeException::class, 'cURL extension is not loaded');
    } else {
        expect(true)->toBeTrue(); // Skip if curl is loaded
    }
});

it('rejects external client instances', function () {
    $fakeClient = new \stdClass();
    expect(fn() => new CurlDriver($this->config, $this->events, $fakeClient))
        ->toThrow(\InvalidArgumentException::class);
});

it('can make a simple GET request', function () {
    $request = new HttpRequest(
        url: $this->baseUrl . '/get?test=value',
        method: 'GET',
        headers: ['User-Agent' => 'instructor-php/test'],
        body: [],
        options: [],
    );

    $response = $this->driver->handle($request);

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('GET')
        ->and($response->isStreamed())->toBeFalse();
})->skip(fn() => !extension_loaded('curl'), 'cURL extension not available');

it('can make a POST request with JSON body', function () {
    $request = new HttpRequest(
        url: $this->baseUrl . '/post',
        method: 'POST',
        headers: [
            'Content-Type' => 'application/json',
            'User-Agent' => 'instructor-php/test'
        ],
        body: ['test' => 'data', 'foo' => 'bar'],
        options: [],
    );

    $response = $this->driver->handle($request);

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('test')
        ->and($response->body())->toContain('data');
})->skip(fn() => !extension_loaded('curl'), 'cURL extension not available');

it('handles headers correctly', function () {
    $request = new HttpRequest(
        url: $this->baseUrl . '/get',
        method: 'GET',
        headers: [
            'X-Custom-Header' => 'test-value',
            'User-Agent' => 'instructor-php/test'
        ],
        body: [],
        options: [],
    );

    $response = $this->driver->handle($request);

    expect($response->statusCode())->toBe(200)
        ->and($response->headers())->toBeArray()
        ->and($response->body())->toContain('headers'); // echoRequest returns headers in response
})->skip(fn() => !extension_loaded('curl'), 'cURL extension not available');

it('can stream response', function () {
    $request = new HttpRequest(
        url: $this->baseUrl . '/get?test=stream',
        method: 'GET',
        headers: ['User-Agent' => 'instructor-php/test'],
        body: [],
        options: ['stream' => true],
    );

    $response = $this->driver->handle($request);

    expect($response->isStreamed())->toBeTrue();

    $chunks = [];
    foreach ($response->stream(64) as $chunk) {
        $chunks[] = $chunk;
    }

    expect($chunks)->not()->toBeEmpty()
        ->and(implode('', $chunks))->toEqual($response->body());
})->skip(fn() => !extension_loaded('curl'), 'cURL extension not available');

it('handles different HTTP methods', function () {
    $methods = ['GET', 'POST', 'PUT', 'DELETE'];

    foreach ($methods as $method) {
        $request = new HttpRequest(
            url: $this->baseUrl . '/' . strtolower($method),
            method: $method,
            headers: [],
            body: $method === 'GET' ? [] : ['test' => 'data'],
            options: [],
        );

        $response = $this->driver->handle($request);
        expect($response->statusCode())->toBe(200);
    }
})->skip(fn() => !extension_loaded('curl'), 'cURL extension not available');

// Clean up server after all tests complete
register_shutdown_function(function() {
    IntegrationTestServer::stop();
});
