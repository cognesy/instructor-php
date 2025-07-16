<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Laravel\LaravelPool;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Success;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\RequestException;

beforeEach(function() {
    $this->mockResponses = [];
    $this->responseIndex = 0;
    
    $this->factory = Mockery::mock(HttpFactory::class);
    $this->events = new EventDispatcher();
    
    $this->config = new HttpClientConfig(
        driver: 'laravel',
        maxConcurrent: 3,
        poolTimeout: 30,
        failOnError: false,
        streamChunkSize: 256
    );
    
    $this->pool = new LaravelPool($this->factory, $this->events, $this->config);
});

afterEach(function() {
    Mockery::close();
});

test('pool with successful requests', function() {
    $responses = [
        Mockery::mock(Response::class)->shouldReceive('status')->andReturn(200)->shouldReceive('failed')->andReturn(false)->getMock(),
        Mockery::mock(Response::class)->shouldReceive('status')->andReturn(200)->shouldReceive('failed')->andReturn(false)->getMock(),
        Mockery::mock(Response::class)->shouldReceive('status')->andReturn(200)->shouldReceive('failed')->andReturn(false)->getMock(),
    ];

    $this->factory->shouldReceive('pool')
        ->once()
        ->with(Mockery::type('callable'))
        ->andReturn($responses);

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

test('pool with failed responses', function() {
    $responses = [
        Mockery::mock(Response::class)->shouldReceive('status')->andReturn(200)->shouldReceive('failed')->andReturn(false)->getMock(),
        Mockery::mock(Response::class)->shouldReceive('status')->andReturn(404)->shouldReceive('failed')->andReturn(true)->shouldReceive('body')->andReturn('Not Found')->getMock(),
    ];

    $this->factory->shouldReceive('pool')
        ->once()
        ->with(Mockery::type('callable'))
        ->andReturn($responses);

    $requests = [
        new HttpRequest('https://example.com/1', 'GET', [], [], []),
        new HttpRequest('https://example.com/2', 'GET', [], [], [])
    ];

    $results = $this->pool->pool($requests);

    expect($results)->toHaveCount(2);
    expect($results[0])->toBeInstanceOf(Success::class);
    expect($results[1])->toBeInstanceOf(Failure::class);
});

test('pool with exception responses', function() {
    $responses = [
        Mockery::mock(Response::class)->shouldReceive('status')->andReturn(200)->shouldReceive('failed')->andReturn(false)->getMock(),
        new \Exception('Connection failed')
    ];

    $this->factory->shouldReceive('pool')
        ->once()
        ->with(Mockery::type('callable'))
        ->andReturn($responses);

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
        driver: 'laravel',
        maxConcurrent: 3,
        poolTimeout: 30,
        failOnError: true,
        streamChunkSize: 256
    );

    $pool = new LaravelPool($this->factory, $this->events, $config);

    $responses = [
        Mockery::mock(Response::class)->shouldReceive('status')->andReturn(200)->shouldReceive('failed')->andReturn(false)->getMock(),
        Mockery::mock(Response::class)->shouldReceive('status')->andReturn(500)->shouldReceive('failed')->andReturn(true)->shouldReceive('body')->andReturn('Internal Server Error')->getMock(),
    ];

    $this->factory->shouldReceive('pool')
        ->once()
        ->with(Mockery::type('callable'))
        ->andReturn($responses);

    $requests = [
        new HttpRequest('https://example.com/1', 'GET', [], [], []),
        new HttpRequest('https://example.com/2', 'GET', [], [], [])
    ];

    expect(fn() => $pool->pool($requests))
        ->toThrow(HttpRequestException::class);
});

test('pool with batched requests', function() {
    $config = new HttpClientConfig(
        driver: 'laravel',
        maxConcurrent: 2,
        poolTimeout: 30,
        failOnError: false,
        streamChunkSize: 256
    );

    $pool = new LaravelPool($this->factory, $this->events, $config);

    $responses1 = [
        Mockery::mock(Response::class)->shouldReceive('status')->andReturn(200)->shouldReceive('failed')->andReturn(false)->getMock(),
        Mockery::mock(Response::class)->shouldReceive('status')->andReturn(200)->shouldReceive('failed')->andReturn(false)->getMock(),
    ];

    $responses2 = [
        Mockery::mock(Response::class)->shouldReceive('status')->andReturn(200)->shouldReceive('failed')->andReturn(false)->getMock(),
    ];

    $this->factory->shouldReceive('pool')
        ->twice()
        ->with(Mockery::type('callable'))
        ->andReturn($responses1, $responses2);

    $requests = [
        new HttpRequest('https://example.com/1', 'GET', [], [], []),
        new HttpRequest('https://example.com/2', 'GET', [], [], []),
        new HttpRequest('https://example.com/3', 'GET', [], [], [])
    ];

    $results = $pool->pool($requests);

    expect($results)->toHaveCount(3);
    expect($results[0])->toBeInstanceOf(Success::class);
    expect($results[1])->toBeInstanceOf(Success::class);
    expect($results[2])->toBeInstanceOf(Success::class);
});

test('pool with post request', function() {
    $responses = [
        Mockery::mock(Response::class)->shouldReceive('status')->andReturn(201)->shouldReceive('failed')->andReturn(false)->getMock(),
    ];

    $this->factory->shouldReceive('pool')
        ->once()
        ->with(Mockery::type('callable'))
        ->andReturn($responses);

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
    $this->factory->shouldReceive('pool')
        ->once()
        ->with(Mockery::type('callable'))
        ->andReturnUsing(function($callback) {
            $pool = Mockery::mock(Pool::class);
            return $callback($pool);
        });

    $requests = ['invalid-request'];
    
    expect(fn() => $this->pool->pool($requests))
        ->toThrow(InvalidArgumentException::class, 'Invalid request type in pool');
});

test('pool with exception in fail on error mode', function() {
    $config = new HttpClientConfig(
        driver: 'laravel',
        maxConcurrent: 3,
        poolTimeout: 30,
        failOnError: true,
        streamChunkSize: 256
    );

    $pool = new LaravelPool($this->factory, $this->events, $config);

    $responses = [
        new \Exception('Connection failed')
    ];

    $this->factory->shouldReceive('pool')
        ->once()
        ->with(Mockery::type('callable'))
        ->andReturn($responses);

    $requests = [
        new HttpRequest('https://example.com/1', 'GET', [], [], [])
    ];

    expect(fn() => $pool->pool($requests))
        ->toThrow(Exception::class, 'Connection failed');
});