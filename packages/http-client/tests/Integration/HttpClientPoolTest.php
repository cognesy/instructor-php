<?php

use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\HttpClient;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Curl\Pool\CurlPool;
use Cognesy\Http\Drivers\Guzzle\GuzzlePool;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\PendingHttpPool;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Success;
use Cognesy\Http\Tests\Support\IntegrationTestServer;

beforeEach(function() {
    // Start local test server for real HTTP integration testing
    $this->baseUrl = IntegrationTestServer::start();
    $this->client = HttpClient::default();
});

afterEach(function() {
    // Server stays running across tests for performance
    // Will be stopped in tearDownAfterClass
});

test('HttpClient pool() method executes requests immediately', function() {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], []),
    );

    $results = $this->client->pool($requests, maxConcurrent: 2);
    $resultArray = $results->all();

    expect($results)->toHaveCount(2);
    expect($resultArray[0])->toBeInstanceOf(Success::class);
    expect($resultArray[1])->toBeInstanceOf(Success::class);
});

test('HttpClient withPool() method returns PendingHttpPool', function() {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], []),
    );

    $pendingPool = $this->client->withPool($requests);

    expect($pendingPool)->toBeInstanceOf(PendingHttpPool::class);
});

test('PendingHttpPool all() method executes deferred requests', function() {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], []),
    );

    $pendingPool = $this->client->withPool($requests);
    $results = $pendingPool->all(maxConcurrent: 2);
    $resultArray = $results->all();

    expect($results)->toHaveCount(2);
    expect($resultArray[0])->toBeInstanceOf(Success::class);
    expect($resultArray[1])->toBeInstanceOf(Success::class);
});

test('pool handles different HTTP methods', function() {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/post', 'POST', [], ['test' => 'data'], []),
        new HttpRequest($this->baseUrl . '/put', 'PUT', [], ['test' => 'data'], []),
    );

    $results = $this->client->pool($requests);

    expect($results)->toHaveCount(3);
    foreach ($results as $result) {
        expect($result)->toBeInstanceOf(Success::class);
    }
});

test('pool with maxConcurrent parameter', function() {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=3', 'GET', [], [], []),
    );

    $results = $this->client->pool($requests, maxConcurrent: 2);
    $resultArray = $results->all();

    expect($results)->toHaveCount(3);
    expect($resultArray[0])->toBeInstanceOf(Success::class);
    expect($resultArray[1])->toBeInstanceOf(Success::class);
    expect($resultArray[2])->toBeInstanceOf(Success::class);
});

test('pool handles request failures gracefully', function() {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/status/500', 'GET', [], [], []),
    );

    $results = $this->client->pool($requests);
    $resultArray = $results->all();

    expect($results)->toHaveCount(2);
    expect($resultArray[0])->toBeInstanceOf(Success::class);
    expect($resultArray[1])->toBeInstanceOf(Failure::class); // 500 status should fail
});

test('pool with empty requests array', function() {
    $results = $this->client->pool(HttpRequestList::empty());

    expect($results)->toHaveCount(0);
});

test('pool with single request', function() {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get', 'GET', [], [], []),
    );

    $results = $this->client->pool($requests);
    $resultArray = $results->all();

    expect($results)->toHaveCount(1);
    expect($resultArray[0])->toBeInstanceOf(Success::class);
});

test('pool concurrent execution works', function() {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=3', 'GET', [], [], []),
    );

    // Test concurrent execution
    $results = $this->client->pool($requests, maxConcurrent: 3);
    $resultArray = $results->all();

    expect($results)->toHaveCount(3);
    expect($resultArray[0])->toBeInstanceOf(Success::class);
    expect($resultArray[1])->toBeInstanceOf(Success::class);
    expect($resultArray[2])->toBeInstanceOf(Success::class);
});

test('different drivers can be used for pooling', function() {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?driver=guzzle', 'GET', [], [], []),
    );

    $guzzleClient = HttpClient::using('guzzle');
    $results = $guzzleClient->pool($requests);
    $resultArray = $results->all();

    expect($results)->toHaveCount(1);
    expect($resultArray[0])->toBeInstanceOf(Success::class);
});

test('withPool uses matching pool handler for selected driver', function() {
    $curlPendingPool = HttpClient::default()->withPool(HttpRequestList::empty());
    $guzzlePendingPool = HttpClient::using('guzzle')->withPool(HttpRequestList::empty());

    $poolHandlerProperty = new \ReflectionProperty(PendingHttpPool::class, 'poolHandler');

    $curlPoolHandler = $poolHandlerProperty->getValue($curlPendingPool);
    $guzzlePoolHandler = $poolHandlerProperty->getValue($guzzlePendingPool);

    expect($curlPoolHandler)->toBeInstanceOf(CurlPool::class);
    expect($guzzlePoolHandler)->toBeInstanceOf(GuzzlePool::class);
});

test('pooling is rejected for external non-pooling drivers', function() {
    $client = (new HttpClientBuilder())
        ->withDriver(new MockHttpDriver())
        ->create();

    expect(fn() => $client->withPool(HttpRequestList::empty()))
        ->toThrow(\InvalidArgumentException::class, 'does not support request pooling');
});

// Clean up server after all tests complete
register_shutdown_function(function() {
    IntegrationTestServer::stop();
});
