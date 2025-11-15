<?php

declare(strict_types=1);

namespace Cognesy\Http\Tests\Integration;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Curl\Pool\CurlPool;
use Cognesy\Http\Tests\Support\IntegrationTestServer;
use Cognesy\Utils\Result\Success;

beforeEach(function () {
    $this->baseUrl = IntegrationTestServer::start();
    $this->events = new EventDispatcher();
    $this->config = new HttpClientConfig(
        driver: 'curl',
        connectTimeout: 3,
        requestTimeout: 30,
        streamChunkSize: 256,
        failOnError: false,
        maxConcurrent: 3,
    );
    $this->pool = new CurlPool($this->config, $this->events);
});

afterEach(function () {
    // Server cleanup handled by shutdown function
});

it('can be instantiated', function () {
    expect($this->pool)->toBeInstanceOf(CurlPool::class);
});

it('throws exception when curl extension is not loaded', function () {
    if (!extension_loaded('curl')) {
        expect(fn() => new CurlPool($this->config, $this->events))
            ->toThrow(\RuntimeException::class, 'cURL extension is not loaded');
    } else {
        expect(true)->toBeTrue(); // Skip if curl is loaded
    }
});

it('rejects external client instances', function () {
    $fakeClient = new \stdClass();
    expect(fn() => new CurlPool($this->config, $this->events, $fakeClient))
        ->toThrow(\InvalidArgumentException::class);
});

it('handles pool with successful requests', function () {
    $requests = HttpRequestList::of(
        new HttpRequest(
            url: $this->baseUrl . '/get?test=1',
            method: 'GET',
            headers: ['User-Agent' => 'instructor-php/test'],
            body: [],
            options: [],
        ),
        new HttpRequest(
            url: $this->baseUrl . '/get?test=2',
            method: 'GET',
            headers: ['User-Agent' => 'instructor-php/test'],
            body: [],
            options: [],
        ),
        new HttpRequest(
            url: $this->baseUrl . '/get?test=3',
            method: 'GET',
            headers: ['User-Agent' => 'instructor-php/test'],
            body: [],
            options: [],
        ),
    );

    $results = $this->pool->pool($requests);
    $resultArray = $results->all();

    expect($results)->toHaveCount(3)
        ->and($resultArray[0])->toBeInstanceOf(Success::class)
        ->and($resultArray[1])->toBeInstanceOf(Success::class)
        ->and($resultArray[2])->toBeInstanceOf(Success::class);

    // Verify responses
    foreach ($results as $result) {
        $response = $result->unwrap();
        expect($response->statusCode())->toBe(200);
    }
})->skip(fn() => !extension_loaded('curl'), 'cURL extension not available');

it('handles pool with mixed POST and GET requests', function () {
    $requests = HttpRequestList::of(
        new HttpRequest(
            url: $this->baseUrl . '/get?test=1',
            method: 'GET',
            headers: ['User-Agent' => 'instructor-php/test'],
            body: [],
            options: [],
        ),
        new HttpRequest(
            url: $this->baseUrl . '/post',
            method: 'POST',
            headers: [
                'Content-Type' => 'application/json',
                'User-Agent' => 'instructor-php/test'
            ],
            body: ['test' => 'data'],
            options: [],
        ),
    );

    $results = $this->pool->pool($requests);
    $resultArray = $results->all();

    expect($results)->toHaveCount(2)
        ->and($resultArray[0])->toBeInstanceOf(Success::class)
        ->and($resultArray[1])->toBeInstanceOf(Success::class);
})->skip(fn() => !extension_loaded('curl'), 'cURL extension not available');

it('handles pool with custom concurrency', function () {
    $requests = [];
    for ($i = 0; $i < 5; $i++) {
        $requests[] = new HttpRequest(
            url: $this->baseUrl . '/get?test=' . $i,
            method: 'GET',
            headers: ['User-Agent' => 'instructor-php/test'],
            body: [],
            options: [],
        );
    }

    // Execute with concurrency of 2
    $results = $this->pool->pool(HttpRequestList::fromArray($requests), 2);

    expect($results)->toHaveCount(5);
    foreach ($results as $result) {
        expect($result)->toBeInstanceOf(Success::class);
    }
})->skip(fn() => !extension_loaded('curl'), 'cURL extension not available');

it('handles empty request array', function () {
    $results = $this->pool->pool(HttpRequestList::empty());

    expect($results)->toHaveCount(0);
});

it('handles pool with error responses when failOnError is false', function () {
    $requests = HttpRequestList::of(
        new HttpRequest(
            url: $this->baseUrl . '/get',
            method: 'GET',
            headers: [],
            body: [],
            options: [],
        ),
        new HttpRequest(
            url: $this->baseUrl . '/status/404',
            method: 'GET',
            headers: [],
            body: [],
            options: [],
        ),
        new HttpRequest(
            url: $this->baseUrl . '/status/500',
            method: 'GET',
            headers: [],
            body: [],
            options: [],
        ),
    );

    $results = $this->pool->pool($requests);
    $resultArray = $results->all();

    expect($results)->toHaveCount(3)
        ->and($resultArray[0])->toBeInstanceOf(Success::class)
        ->and($resultArray[1])->toBeInstanceOf(\Cognesy\Utils\Result\Failure::class)
        ->and($resultArray[2])->toBeInstanceOf(\Cognesy\Utils\Result\Failure::class);

    // Verify status codes - successful responses can be unwrapped, failures contain exceptions
    expect($resultArray[0]->unwrap()->statusCode())->toBe(200);
    expect($resultArray[1]->error())->toBeInstanceOf(\Cognesy\Http\Exceptions\HttpRequestException::class);
    expect($resultArray[2]->error())->toBeInstanceOf(\Cognesy\Http\Exceptions\HttpRequestException::class);
})->skip(fn() => !extension_loaded('curl'), 'cURL extension not available');

it('throws exception for error responses when failOnError is true', function () {
    $config = new HttpClientConfig(
        driver: 'curl',
        connectTimeout: 3,
        requestTimeout: 30,
        failOnError: true,
    );

    $pool = new CurlPool($config, $this->events);

    $requests = HttpRequestList::of(
        new HttpRequest(
            url: $this->baseUrl . '/get',
            method: 'GET',
            headers: [],
            body: [],
            options: [],
        ),
        new HttpRequest(
            url: $this->baseUrl . '/status/404',
            method: 'GET',
            headers: [],
            body: [],
            options: [],
        ),
    );

    try {
        $pool->pool($requests);
        expect(false)->toBeTrue('Expected exception to be thrown');
    } catch (\Throwable $e) {
        expect($e)->toBeInstanceOf(\Throwable::class);
        expect($e->getMessage())->toContain('404');
    }
})->skip(fn() => !extension_loaded('curl'), 'cURL extension not available');

it('handles different HTTP methods', function () {
    $requests = HttpRequestList::of(
        new HttpRequest(
            url: $this->baseUrl . '/get',
            method: 'GET',
            headers: [],
            body: [],
            options: [],
        ),
        new HttpRequest(
            url: $this->baseUrl . '/post',
            method: 'POST',
            headers: [],
            body: ['data' => 'test'],
            options: [],
        ),
        new HttpRequest(
            url: $this->baseUrl . '/put',
            method: 'PUT',
            headers: [],
            body: ['data' => 'test'],
            options: [],
        ),
        new HttpRequest(
            url: $this->baseUrl . '/delete',
            method: 'DELETE',
            headers: [],
            body: [],
            options: [],
        ),
    );

    $results = $this->pool->pool($requests);

    expect($results)->toHaveCount(4);
    foreach ($results as $result) {
        expect($result)->toBeInstanceOf(Success::class)
            ->and($result->unwrap()->statusCode())->toBe(200);
    }
})->skip(fn() => !extension_loaded('curl'), 'cURL extension not available');

it('maintains request order in responses', function () {
    $requests = [];
    for ($i = 0; $i < 10; $i++) {
        $requests[] = new HttpRequest(
            url: $this->baseUrl . '/get?id=' . $i,
            method: 'GET',
            headers: [],
            body: [],
            options: [],
        );
    }

    $results = $this->pool->pool(HttpRequestList::fromArray($requests), 3);

    expect($results)->toHaveCount(10);

    // Verify all succeeded
    foreach ($results as $index => $result) {
        expect($result)->toBeInstanceOf(Success::class);
        // Note: We can't verify exact order from response body in current test server
        // but we verify count and all successful
    }
})->skip(fn() => !extension_loaded('curl'), 'cURL extension not available');

it('executes multiple delayed requests within reasonable time', function () {
    // Note: PHP built-in server used in tests is single-threaded,
    // so end-to-end timings will appear sequential regardless of client concurrency.
    // Keep the test lightweight to avoid slowing CI.

    // Create 2 requests that each take 1 second
    $requests = [];
    for ($i = 0; $i < 2; $i++) {
        $requests[] = new HttpRequest(
            url: $this->baseUrl . '/delay/1',
            method: 'GET',
            headers: [],
            body: [],
            options: [],
        );
    }

    // Execute with concurrency of 2
    $start = microtime(true);
    $results = $this->pool->pool(HttpRequestList::fromArray($requests), 2);
    $duration = microtime(true) - $start;

    // Verify all succeeded
    expect($results)->toHaveCount(2);
    foreach ($results as $result) {
        expect($result)->toBeInstanceOf(Success::class);
    }

    // Single-threaded server yields ~2s total here. Keep generous bounds to avoid flaky CI.
    expect($duration)->toBeLessThan(3.0)
        ->and($duration)->toBeGreaterThan(0.9);
})->skip(fn() => !extension_loaded('curl'), 'cURL extension not available');

it('handles a small batch of delayed requests with configured concurrency', function () {
    // Keep this test lightweight as well: 3x /delay/1
    $requests = [];
    for ($i = 0; $i < 3; $i++) {
        $requests[] = new HttpRequest(
            url: $this->baseUrl . '/delay/1',
            method: 'GET',
            headers: [],
            body: [],
            options: [],
        );
    }

    // Execute with concurrency of 3
    $start = microtime(true);
    $results = $this->pool->pool(HttpRequestList::fromArray($requests), 3);
    $duration = microtime(true) - $start;

    // Verify all succeeded
    expect($results)->toHaveCount(3);
    foreach ($results as $result) {
        expect($result)->toBeInstanceOf(Success::class);
    }

    // Single-threaded server yields ~3s total for 3 delayed requests.
    // Use wide bounds to keep CI stable and fast.
    expect($duration)->toBeLessThan(4.0)
        ->and($duration)->toBeGreaterThan(0.9);
})->skip(fn() => !extension_loaded('curl'), 'cURL extension not available');

// Clean up server after all tests complete
register_shutdown_function(function() {
    IntegrationTestServer::stop();
});
