<?php

use Cognesy\Http\HttpClient;
use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\PendingHttpPool;
use Cognesy\Utils\Result\Success;
use Cognesy\Utils\Result\Failure;
use Cognesy\Http\Tests\Support\IntegrationTestServer;

beforeEach(function() {
    // Start local test server for real HTTP integration testing
    $this->baseUrl = IntegrationTestServer::start();
    $this->client = HttpClient::default();
});

afterEach(function() {
    // Server stays running across tests for performance
    // Will be stopped in shutdown function
});

test('PendingHttpPool can be created with requests', function() {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], [])
    );

    $pendingPool = $this->client->withPool($requests);

    expect($pendingPool)->toBeInstanceOf(PendingHttpPool::class);
});

test('PendingHttpPool all() executes all requests', function() {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=3', 'GET', [], [], [])
    );

    $pendingPool = $this->client->withPool($requests);
    $results = $pendingPool->all();
    $resultArray = $results->all();

    expect($results)->toHaveCount(3);
    foreach ($results as $result) {
        expect($result)->toBeInstanceOf(Success::class);
    }
});

test('PendingHttpPool all() respects maxConcurrent parameter', function() {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=3', 'GET', [], [], [])
    );

    $pendingPool = $this->client->withPool($requests);
    
    // Test with maxConcurrent=2
    $results = $pendingPool->all(maxConcurrent: 2);
    $resultArray = $results->all();
    $resultArray = $results->all();

    expect($results)->toHaveCount(3);
    expect($resultArray[0])->toBeInstanceOf(Success::class);
    expect($resultArray[1])->toBeInstanceOf(Success::class);
    expect($resultArray[2])->toBeInstanceOf(Success::class);
});

test('PendingHttpPool all() with no maxConcurrent uses default', function() {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], [])
    );

    $pendingPool = $this->client->withPool($requests);
    $results = $pendingPool->all();
    $resultArray = $results->all();

    expect($results)->toHaveCount(2);
    expect($resultArray[0])->toBeInstanceOf(Success::class);
    expect($resultArray[1])->toBeInstanceOf(Success::class);
});

test('PendingHttpPool can be reused multiple times', function() {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=reuse', 'GET', [], [], [])
    );

    $pendingPool = $this->client->withPool($requests);
    
    // Execute first time
    $results1 = $pendingPool->all();
    expect($results1)->toHaveCount(1);
    expect($results1->all()[0])->toBeInstanceOf(Success::class);
    
    // Execute second time
    $results2 = $pendingPool->all();
    expect($results2)->toHaveCount(1);
    expect($results2->all()[0])->toBeInstanceOf(Success::class);
});

test('PendingHttpPool handles empty requests array', function() {
    $pendingPool = $this->client->withPool(HttpRequestList::empty());
    $results = $pendingPool->all();
    $resultArray = $results->all();

    expect($results)->toHaveCount(0);});

test('PendingHttpPool handles mixed success and failure results', function() {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/status/500', 'GET', [], [], [])
    );

    $pendingPool = $this->client->withPool($requests);
    $results = $pendingPool->all();
    $resultArray = $results->all();

    expect($results)->toHaveCount(2);
    expect($resultArray[0])->toBeInstanceOf(Success::class);
    expect($resultArray[1])->toBeInstanceOf(Failure::class); // 500 status should fail
});

test('PendingHttpPool with POST requests', function() {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/post', 'POST', [], ['key1' => 'value1'], []),
        new HttpRequest($this->baseUrl . '/post', 'POST', [], ['key2' => 'value2'], [])
    );

    $pendingPool = $this->client->withPool($requests);
    $results = $pendingPool->all();
    $resultArray = $results->all();

    expect($results)->toHaveCount(2);
    expect($resultArray[0])->toBeInstanceOf(Success::class);
    expect($resultArray[1])->toBeInstanceOf(Success::class);
});

test('PendingHttpPool concurrent execution performance', function() {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=3', 'GET', [], [], [])
    );

    $pendingPool = $this->client->withPool($requests);
    
    // Test concurrent execution
    $results = $pendingPool->all(maxConcurrent: 3);
    $resultArray = $results->all();
    $resultArray = $results->all();

    expect($results)->toHaveCount(3);
    expect($resultArray[0])->toBeInstanceOf(Success::class);
    expect($resultArray[1])->toBeInstanceOf(Success::class);
    expect($resultArray[2])->toBeInstanceOf(Success::class);
});

test('PendingHttpPool works with different HTTP client drivers', function() {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?driver=test', 'GET', [], [], [])
    );

    $guzzleClient = HttpClient::using('guzzle');
    $pendingPool = $guzzleClient->withPool($requests);
    $results = $pendingPool->all();
    $resultArray = $results->all();

    expect($results)->toHaveCount(1);
    expect($resultArray[0])->toBeInstanceOf(Success::class);
});

// Clean up server after all tests complete
register_shutdown_function(function() {
    IntegrationTestServer::stop();
});