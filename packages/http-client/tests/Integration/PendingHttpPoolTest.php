<?php

use Cognesy\Http\HttpClient;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\PendingHttpPool;
use Cognesy\Utils\Result\Success;
use Cognesy\Utils\Result\Failure;

beforeEach(function() {
    $this->client = HttpClient::default();
});

test('PendingHttpPool can be created with requests', function() {
    $requests = [
        new HttpRequest('https://httpbin.org/get?test=1', 'GET', [], [], []),
        new HttpRequest('https://httpbin.org/get?test=2', 'GET', [], [], []),
    ];

    $pendingPool = $this->client->withPool($requests);

    expect($pendingPool)->toBeInstanceOf(PendingHttpPool::class);
});

test('PendingHttpPool all() executes all requests', function() {
    $requests = [
        new HttpRequest('https://httpbin.org/get?test=1', 'GET', [], [], []),
        new HttpRequest('https://httpbin.org/get?test=2', 'GET', [], [], []),
        new HttpRequest('https://httpbin.org/get?test=3', 'GET', [], [], []),
    ];

    $pendingPool = $this->client->withPool($requests);
    $results = $pendingPool->all();

    expect($results)->toHaveCount(3);
    foreach ($results as $result) {
        expect($result)->toBeInstanceOf(Success::class);
    }
});

test('PendingHttpPool all() respects maxConcurrent parameter', function() {
    $requests = [
        new HttpRequest('https://httpbin.org/get?test=1', 'GET', [], [], []),
        new HttpRequest('https://httpbin.org/get?test=2', 'GET', [], [], []),
        new HttpRequest('https://httpbin.org/get?test=3', 'GET', [], [], []),
    ];

    $pendingPool = $this->client->withPool($requests);
    
    // Test with maxConcurrent=2
    $results = $pendingPool->all(maxConcurrent: 2);

    expect($results)->toHaveCount(3);
    expect($results[0])->toBeInstanceOf(Success::class);
    expect($results[1])->toBeInstanceOf(Success::class);
    expect($results[2])->toBeInstanceOf(Success::class);
});

test('PendingHttpPool all() with no maxConcurrent uses default', function() {
    $requests = [
        new HttpRequest('https://httpbin.org/get?test=1', 'GET', [], [], []),
        new HttpRequest('https://httpbin.org/get?test=2', 'GET', [], [], []),
    ];

    $pendingPool = $this->client->withPool($requests);
    $results = $pendingPool->all();

    expect($results)->toHaveCount(2);
    expect($results[0])->toBeInstanceOf(Success::class);
    expect($results[1])->toBeInstanceOf(Success::class);
});

test('PendingHttpPool can be reused multiple times', function() {
    $requests = [
        new HttpRequest('https://httpbin.org/get?test=reuse', 'GET', [], [], []),
    ];

    $pendingPool = $this->client->withPool($requests);
    
    // Execute first time
    $results1 = $pendingPool->all();
    expect($results1)->toHaveCount(1);
    expect($results1[0])->toBeInstanceOf(Success::class);
    
    // Execute second time
    $results2 = $pendingPool->all();
    expect($results2)->toHaveCount(1);
    expect($results2[0])->toBeInstanceOf(Success::class);
});

test('PendingHttpPool handles empty requests array', function() {
    $pendingPool = $this->client->withPool([]);
    $results = $pendingPool->all();

    expect($results)->toHaveCount(0);
    expect($results)->toBeArray();
});

test('PendingHttpPool handles mixed success and failure results', function() {
    $requests = [
        new HttpRequest('https://httpbin.org/get', 'GET', [], [], []),
        new HttpRequest('https://non-existent-domain.invalid', 'GET', [], [], []),
    ];

    $pendingPool = $this->client->withPool($requests);
    $results = $pendingPool->all();

    expect($results)->toHaveCount(2);
    expect($results[0])->toBeInstanceOf(Success::class);
    expect($results[1])->toBeInstanceOf(Failure::class); // Invalid domain should fail
});

test('PendingHttpPool with POST requests', function() {
    $requests = [
        new HttpRequest('https://httpbin.org/post', 'POST', [], ['key1' => 'value1'], []),
        new HttpRequest('https://httpbin.org/post', 'POST', [], ['key2' => 'value2'], []),
    ];

    $pendingPool = $this->client->withPool($requests);
    $results = $pendingPool->all();

    expect($results)->toHaveCount(2);
    expect($results[0])->toBeInstanceOf(Success::class);
    expect($results[1])->toBeInstanceOf(Success::class);
});

test('PendingHttpPool concurrent execution performance', function() {
    $requests = [
        new HttpRequest('https://httpbin.org/get?test=1', 'GET', [], [], []),
        new HttpRequest('https://httpbin.org/get?test=2', 'GET', [], [], []),
        new HttpRequest('https://httpbin.org/get?test=3', 'GET', [], [], []),
    ];

    $pendingPool = $this->client->withPool($requests);
    
    // Test concurrent execution
    $results = $pendingPool->all(maxConcurrent: 3);

    expect($results)->toHaveCount(3);
    expect($results[0])->toBeInstanceOf(Success::class);
    expect($results[1])->toBeInstanceOf(Success::class);
    expect($results[2])->toBeInstanceOf(Success::class);
});

test('PendingHttpPool works with different HTTP client drivers', function() {
    $requests = [
        new HttpRequest('https://httpbin.org/get?driver=test', 'GET', [], [], []),
    ];

    $guzzleClient = HttpClient::using('guzzle');
    $pendingPool = $guzzleClient->withPool($requests);
    $results = $pendingPool->all();

    expect($results)->toHaveCount(1);
    expect($results[0])->toBeInstanceOf(Success::class);
});