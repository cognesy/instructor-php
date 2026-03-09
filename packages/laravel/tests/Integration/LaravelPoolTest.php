<?php declare(strict_types=1);

require_once __DIR__ . '/../Support/HttpTestRouter.php';
require_once __DIR__ . '/../Support/IntegrationTestServer.php';

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\HttpPool\Config\HttpPoolConfig;
use Cognesy\Instructor\Laravel\HttpPool\LaravelPool;
use Cognesy\Instructor\Laravel\Tests\Support\IntegrationTestServer;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Success;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;

beforeEach(function() {
    $this->baseUrl = IntegrationTestServer::start();
    $this->factory = Mockery::mock(HttpFactory::class);
    $this->events = new EventDispatcher();
    $this->config = new HttpPoolConfig(
        driver: 'laravel',
        maxConcurrent: 3,
        poolTimeout: 30,
        failOnError: false,
        streamChunkSize: 256,
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

    $this->factory->shouldReceive('pool')->once()->with(Mockery::type('callable'))->andReturn($responses);

    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=3', 'GET', [], [], []),
    );

    $results = $this->pool->pool($requests)->all();

    expect($results[0])->toBeInstanceOf(Success::class)
        ->and($results[1])->toBeInstanceOf(Success::class)
        ->and($results[2])->toBeInstanceOf(Success::class);
});

test('pool with failed responses', function() {
    $responses = [
        Mockery::mock(Response::class)->shouldReceive('status')->andReturn(200)->shouldReceive('failed')->andReturn(false)->getMock(),
        Mockery::mock(Response::class)->shouldReceive('status')->andReturn(404)->shouldReceive('failed')->andReturn(true)->shouldReceive('body')->andReturn('Not Found')->getMock(),
    ];

    $this->factory->shouldReceive('pool')->once()->with(Mockery::type('callable'))->andReturn($responses);

    $results = $this->pool->pool(HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], []),
    ))->all();

    expect($results[0])->toBeInstanceOf(Success::class)
        ->and($results[1])->toBeInstanceOf(Failure::class);
});

test('pool with fail on error true', function() {
    $pool = new LaravelPool($this->factory, $this->events, new HttpPoolConfig(
        driver: 'laravel',
        maxConcurrent: 3,
        poolTimeout: 30,
        failOnError: true,
        streamChunkSize: 256,
    ));

    $responses = [
        Mockery::mock(Response::class)->shouldReceive('status')->andReturn(200)->shouldReceive('failed')->andReturn(false)->getMock(),
        Mockery::mock(Response::class)->shouldReceive('status')->andReturn(500)->shouldReceive('failed')->andReturn(true)->shouldReceive('body')->andReturn('Internal Server Error')->getMock(),
    ];

    $this->factory->shouldReceive('pool')->once()->with(Mockery::type('callable'))->andReturn($responses);

    expect(fn() => $pool->pool(HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], []),
    )))->toThrow(HttpRequestException::class);
});

test('pool with batched requests', function() {
    $pool = new LaravelPool($this->factory, $this->events, new HttpPoolConfig(
        driver: 'laravel',
        maxConcurrent: 2,
        poolTimeout: 30,
        failOnError: false,
        streamChunkSize: 256,
    ));

    $this->factory->shouldReceive('pool')
        ->twice()
        ->with(Mockery::type('callable'))
        ->andReturn(
            [
                Mockery::mock(Response::class)->shouldReceive('status')->andReturn(200)->shouldReceive('failed')->andReturn(false)->getMock(),
                Mockery::mock(Response::class)->shouldReceive('status')->andReturn(200)->shouldReceive('failed')->andReturn(false)->getMock(),
            ],
            [
                Mockery::mock(Response::class)->shouldReceive('status')->andReturn(200)->shouldReceive('failed')->andReturn(false)->getMock(),
            ],
        );

    $results = $pool->pool(HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=3', 'GET', [], [], []),
    ))->all();

    expect($results[0])->toBeInstanceOf(Success::class)
        ->and($results[1])->toBeInstanceOf(Success::class)
        ->and($results[2])->toBeInstanceOf(Success::class);
});

test('pool with empty request array', function() {
    expect($this->pool->pool(HttpRequestList::empty()))->toHaveCount(0);
});

register_shutdown_function(function() {
    IntegrationTestServer::stop();
});
