<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Laravel\LaravelPool;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Success;
use Cognesy\Http\Tests\Support\IntegrationTestServer;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\RequestException;

beforeEach(function() {
    // Start local test server for consistent URL handling
    $this->baseUrl = IntegrationTestServer::start();
    
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
    // Server stays running across tests for performance
    // Will be stopped in shutdown function
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

test('pool with failed responses', function() {
    $responses = [
        Mockery::mock(Response::class)->shouldReceive('status')->andReturn(200)->shouldReceive('failed')->andReturn(false)->getMock(),
        Mockery::mock(Response::class)->shouldReceive('status')->andReturn(404)->shouldReceive('failed')->andReturn(true)->shouldReceive('body')->andReturn('Not Found')->getMock(),
    ];

    $this->factory->shouldReceive('pool')
        ->once()
        ->with(Mockery::type('callable'))
        ->andReturn($responses);

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

test('pool with exception responses', function() {
    $responses = [
        Mockery::mock(Response::class)->shouldReceive('status')->andReturn(200)->shouldReceive('failed')->andReturn(false)->getMock(),
        new \Exception('Connection failed')
    ];

    $this->factory->shouldReceive('pool')
        ->once()
        ->with(Mockery::type('callable'))
        ->andReturn($responses);

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

    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], [])
    );

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

    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=3', 'GET', [], [], [])
    );

    $results = $pool->pool($requests);
    $resultArray = $results->all();
    $resultArray = $results->all();

    expect($results)->toHaveCount(3);
    expect($resultArray[0])->toBeInstanceOf(Success::class);
    expect($resultArray[1])->toBeInstanceOf(Success::class);
    expect($resultArray[2])->toBeInstanceOf(Success::class);
});

test('pool with post request', function() {
    $responses = [
        Mockery::mock(Response::class)->shouldReceive('status')->andReturn(201)->shouldReceive('failed')->andReturn(false)->getMock(),
    ];

    $this->factory->shouldReceive('pool')
        ->once()
        ->with(Mockery::type('callable'))
        ->andReturn($responses);

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

    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], [])
    );

    expect(fn() => $pool->pool($requests))
        ->toThrow(Exception::class, 'Connection failed');
});

// Clean up server after all tests complete
register_shutdown_function(function() {
    IntegrationTestServer::stop();
});