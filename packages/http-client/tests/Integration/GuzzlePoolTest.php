<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Guzzle\GuzzlePool;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Success;
use Cognesy\Http\Tests\Support\IntegrationTestServer;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

beforeEach(function() {
    // Start local test server for consistent URL handling
    $this->baseUrl = IntegrationTestServer::start();

    $this->mockHandler = new MockHandler();
    $handlerStack = HandlerStack::create($this->mockHandler);
    $this->client = new Client(['handler' => $handlerStack]);
    $this->events = new EventDispatcher();

    $this->config = new HttpClientConfig(
        driver: 'guzzle',
        maxConcurrent: 3,
        poolTimeout: 30,
        failOnError: false
    );

    $this->pool = new GuzzlePool($this->config, $this->client, $this->events);
});

afterEach(function() {
    // Server stays running across tests for performance
    // Will be stopped in shutdown function
});

test('pool with successful requests', function() {
    $this->mockHandler->append(
        new Response(200, [], 'Response 1'),
        new Response(200, [], 'Response 2'),
        new Response(200, [], 'Response 3')
    );

    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=3', 'GET', [], [], [])
    );

    $results = $this->pool->pool($requests);
    $resultArray = $results->all();

    expect($results)->toHaveCount(3);
    expect($resultArray[0])->toBeInstanceOf(Success::class);
    expect($resultArray[1])->toBeInstanceOf(Success::class);
    expect($resultArray[2])->toBeInstanceOf(Success::class);
});

test('pool with mixed results', function() {
    $this->mockHandler->append(
        new Response(200, [], 'Success'),
        new RequestException('Network error', new Request('GET', $this->baseUrl . '/get?test=2'))
    );

    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], [])
    );

    $results = $this->pool->pool($requests);
    $resultArray = $results->all();

    expect($results)->toHaveCount(2);
    expect($resultArray[0])->toBeInstanceOf(Success::class);
    expect($resultArray[1])->toBeInstanceOf(Failure::class);
});

test('pool with fail on error true', function() {
    $config = new HttpClientConfig(
        driver: 'guzzle',
        maxConcurrent: 3,
        poolTimeout: 30,
        failOnError: true
    );

    $pool = new GuzzlePool(
        $config,
        new Client(['handler' => HandlerStack::create($this->mockHandler)]),
        new EventDispatcher()
    );

    $this->mockHandler->append(
        new Response(200, [], 'Success'),
        new RequestException('Network error', new Request('GET', $this->baseUrl . '/get?test=2'))
    );

    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], [])
    );

    expect(fn() => $pool->pool($requests))
        ->toThrow(HttpRequestException::class);
});

test('pool with custom concurrency', function() {
    $this->mockHandler->append(
        new Response(200, [], 'Response 1'),
        new Response(200, [], 'Response 2')
    );

    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], [])
    );

    $results = $this->pool->pool($requests, 1);
    $resultArray = $results->all();

    expect($results)->toHaveCount(2);
    expect($resultArray[0])->toBeInstanceOf(Success::class);
    expect($resultArray[1])->toBeInstanceOf(Success::class);
});

test('pool with streamed response', function() {
    $this->mockHandler->append(
        new Response(200, ['Content-Type' => 'text/event-stream'], 'data: test\n\n')
    );

    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/stream/5', 'GET', [], [], [])
    );

    $results = $this->pool->pool($requests);
    $resultArray = $results->all();

    expect($results)->toHaveCount(1);
    expect($resultArray[0])->toBeInstanceOf(Success::class);
});

test('pool with post request', function() {
    $this->mockHandler->append(
        new Response(201, [], 'Created')
    );

    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/post', 'POST', ['Content-Type' => 'application/json'], '{"test": "data"}', [])
    );

    $results = $this->pool->pool($requests);
    $resultArray = $results->all();

    expect($results)->toHaveCount(1);
    expect($resultArray[0])->toBeInstanceOf(Success::class);
});

test('pool with empty request array', function() {
    $results = $this->pool->pool(HttpRequestList::empty());

    expect($results)->toHaveCount(0);
});

test('pool with invalid request type', function() {
    expect(fn() => HttpRequestList::of('invalid-request'))
        ->toThrow(\TypeError::class);
});

// Clean up server after all tests complete
register_shutdown_function(function() {
    IntegrationTestServer::stop();
});
