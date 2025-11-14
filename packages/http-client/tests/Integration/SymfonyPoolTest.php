<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Symfony\SymfonyPool;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Success;
use Cognesy\Http\Tests\Support\IntegrationTestServer;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

beforeEach(function() {
    // Start local test server for consistent URL handling
    $this->baseUrl = IntegrationTestServer::start();
    
    $this->mockResponses = [];
    $this->responseIndex = 0;
    $this->mockClient = new MockHttpClient(function($method, $url, $options) {
        $response = $this->mockResponses[$this->responseIndex++] ?? new MockResponse('Default response', ['http_code' => 200]);
        return $response;
    });
    
    $this->events = new EventDispatcher();
    
    $this->config = new HttpClientConfig(
        driver: 'symfony',
        maxConcurrent: 3,
        poolTimeout: 30,
        failOnError: false
    );
    
    $this->pool = new SymfonyPool($this->mockClient, $this->config, $this->events);
});

afterEach(function() {
    // Server stays running across tests for performance
    // Will be stopped in shutdown function
});

test('pool with successful requests', function() {
    $this->responseIndex = 0;
    $this->mockResponses = [
        new MockResponse('Response 1', ['http_code' => 200]),
        new MockResponse('Response 2', ['http_code' => 200]),
        new MockResponse('Response 3', ['http_code' => 200])
    ];

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

test('pool with error handling', function() {
    $this->responseIndex = 0;
    $this->mockResponses = [
        new MockResponse('Not Found', ['http_code' => 404])
    ];

    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/status/404', 'GET', [], [], [])
    );

    $results = $this->pool->pool($requests);
    $resultArray = $results->all();

    expect($results)->toHaveCount(1);
    expect($resultArray[0])->toBeInstanceOf(Failure::class);
});

test('pool with fail on error true', function() {
    $this->responseIndex = 0;
    $config = new HttpClientConfig(
        driver: 'symfony',
        maxConcurrent: 3,
        poolTimeout: 30,
        failOnError: true
    );

    $this->mockResponses = [
        new MockResponse('Success', ['http_code' => 200]),
        new MockResponse('Server Error', ['http_code' => 500])
    ];

    $pool = new SymfonyPool($this->mockClient, $config, $this->events);

    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], [])
    );

    expect(fn() => $pool->pool($requests))
        ->toThrow(HttpRequestException::class);
});

test('pool processes all requests', function() {
    $this->responseIndex = 0;
    $this->mockResponses = [
        new MockResponse('Response 1', ['http_code' => 200]),
        new MockResponse('Response 2', ['http_code' => 200])
    ];

    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], [])
    );

    $results = $this->pool->pool($requests);
    $resultArray = $results->all();

    expect($results)->toHaveCount(2);
    expect($resultArray[0])->toBeInstanceOf(Success::class);
    expect($resultArray[1])->toBeInstanceOf(Success::class);
});

test('pool with streamed response', function() {
    $this->responseIndex = 0;
    $this->mockResponses = [
        new MockResponse('data: test\n\n', [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'text/event-stream']
        ])
    ];

    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/stream/5', 'GET', [], [], [])
    );

    $results = $this->pool->pool($requests);
    $resultArray = $results->all();

    expect($results)->toHaveCount(1);
    expect($resultArray[0])->toBeInstanceOf(Success::class);
});

test('pool with post request', function() {
    $this->responseIndex = 0;
    $this->mockResponses = [
        new MockResponse('Created', ['http_code' => 201])
    ];

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

    expect($results)->toHaveCount(0);});

test('pool with invalid request type', function() {
    $requests = ['invalid-request'];
    
    expect(fn() => HttpRequestList::of(...$requests))
        ->toThrow(\TypeError::class);
    expect(fn() => HttpRequestList::of(...$requests))
        ->toThrow(\TypeError::class);
});

test('pool with timeout handling', function() {
    $this->responseIndex = 0;
    $config = new HttpClientConfig(
        driver: 'symfony',
        maxConcurrent: 3,
        poolTimeout: 1,
        failOnError: false
    );

    $this->mockResponses = [
        new MockResponse('Success', ['http_code' => 200]),
    ];

    $pool = new SymfonyPool($this->mockClient, $config, $this->events);

    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], [])
    );

    $results = $pool->pool($requests);
    $resultArray = $results->all();
    $resultArray = $results->all();

    expect($results)->toHaveCount(1);
    expect($resultArray[0])->toBeInstanceOf(Success::class);
});

test('pool with client error status code', function() {
    $this->responseIndex = 0;
    $this->mockResponses = [
        new MockResponse('Bad Request', ['http_code' => 400]),
        new MockResponse('Unauthorized', ['http_code' => 401])
    ];

    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], [])
    );

    $results = $this->pool->pool($requests);
    $resultArray = $results->all();

    expect($results)->toHaveCount(2);
    expect($resultArray[0])->toBeInstanceOf(Failure::class);
    expect($resultArray[1])->toBeInstanceOf(Failure::class);
});

// Clean up server after all tests complete
register_shutdown_function(function() {
    IntegrationTestServer::stop();
});