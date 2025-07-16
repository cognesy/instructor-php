<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Guzzle\GuzzlePool;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Success;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

beforeEach(function() {
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

test('pool with successful requests', function() {
    $this->mockHandler->append(
        new Response(200, [], 'Response 1'),
        new Response(200, [], 'Response 2'),
        new Response(200, [], 'Response 3')
    );

    $requests = [
        new HttpRequest('https://example.com/1', 'GET', [], [], []),
        new HttpRequest('https://example.com/2', 'GET', [], [], []),
        new HttpRequest('https://example.com/3', 'GET', [], [], [])
    ];

    $results = $this->pool->pool($requests);

    expect($results)->toHaveCount(3);
    expect($results[0])->toBeInstanceOf(Success::class);
    expect($results[1])->toBeInstanceOf(Success::class);
    expect($results[2])->toBeInstanceOf(Success::class);
});

test('pool with mixed results', function() {
    $this->mockHandler->append(
        new Response(200, [], 'Success'),
        new RequestException('Network error', new Request('GET', 'https://example.com/2'))
    );

    $requests = [
        new HttpRequest('https://example.com/1', 'GET', [], [], []),
        new HttpRequest('https://example.com/2', 'GET', [], [], [])
    ];

    $results = $this->pool->pool($requests);

    expect($results)->toHaveCount(2);
    expect($results[0])->toBeInstanceOf(Success::class);
    expect($results[1])->toBeInstanceOf(Failure::class);
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
        new RequestException('Network error', new Request('GET', 'https://example.com/2'))
    );

    $requests = [
        new HttpRequest('https://example.com/1', 'GET', [], [], []),
        new HttpRequest('https://example.com/2', 'GET', [], [], [])
    ];

    expect(fn() => $pool->pool($requests))
        ->toThrow(HttpRequestException::class);
});

test('pool with custom concurrency', function() {
    $this->mockHandler->append(
        new Response(200, [], 'Response 1'),
        new Response(200, [], 'Response 2')
    );

    $requests = [
        new HttpRequest('https://example.com/1', 'GET', [], [], []),
        new HttpRequest('https://example.com/2', 'GET', [], [], [])
    ];

    $results = $this->pool->pool($requests, 1);

    expect($results)->toHaveCount(2);
    expect($results[0])->toBeInstanceOf(Success::class);
    expect($results[1])->toBeInstanceOf(Success::class);
});

test('pool with streamed response', function() {
    $this->mockHandler->append(
        new Response(200, ['Content-Type' => 'text/event-stream'], 'data: test\n\n')
    );

    $requests = [
        new HttpRequest('https://example.com/stream', 'GET', [], [], [])
    ];

    $results = $this->pool->pool($requests);

    expect($results)->toHaveCount(1);
    expect($results[0])->toBeInstanceOf(Success::class);
});

test('pool with post request', function() {
    $this->mockHandler->append(
        new Response(201, [], 'Created')
    );

    $requests = [
        new HttpRequest('https://example.com/api', 'POST', ['Content-Type' => 'application/json'], '{"test": "data"}', [])
    ];

    $results = $this->pool->pool($requests);

    expect($results)->toHaveCount(1);
    expect($results[0])->toBeInstanceOf(Success::class);
});

test('pool with empty request array', function() {
    $results = $this->pool->pool([]);

    expect($results)->toHaveCount(0);
    expect($results)->toBeArray();
});

test('pool with invalid request type', function() {
    $requests = ['invalid-request'];
    
    expect(fn() => $this->pool->pool($requests))
        ->toThrow(InvalidArgumentException::class, 'Invalid request type in pool');
});