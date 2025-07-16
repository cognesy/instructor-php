<?php

use Cognesy\Http\HttpClient;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\PendingHttpPool;
use Cognesy\Utils\Result\Success;
use Cognesy\Utils\Result\Failure;

beforeEach(function() {
    $this->client = HttpClient::default();
});

test('HttpClient pool() method executes requests immediately', function() {
    $requests = [
        new HttpRequest('https://httpbin.org/get?test=1', 'GET', [], [], []),
        new HttpRequest('https://httpbin.org/get?test=2', 'GET', [], [], []),
    ];

    $results = $this->client->pool($requests, maxConcurrent: 2);

    expect($results)->toHaveCount(2);
    expect($results[0])->toBeInstanceOf(Success::class);
    expect($results[1])->toBeInstanceOf(Success::class);
});

test('HttpClient withPool() method returns PendingHttpPool', function() {
    $requests = [
        new HttpRequest('https://httpbin.org/get?test=1', 'GET', [], [], []),
        new HttpRequest('https://httpbin.org/get?test=2', 'GET', [], [], []),
    ];

    $pendingPool = $this->client->withPool($requests);

    expect($pendingPool)->toBeInstanceOf(PendingHttpPool::class);
});

test('PendingHttpPool all() method executes deferred requests', function() {
    $requests = [
        new HttpRequest('https://httpbin.org/get?test=1', 'GET', [], [], []),
        new HttpRequest('https://httpbin.org/get?test=2', 'GET', [], [], []),
    ];

    $pendingPool = $this->client->withPool($requests);
    $results = $pendingPool->all(maxConcurrent: 2);

    expect($results)->toHaveCount(2);
    expect($results[0])->toBeInstanceOf(Success::class);
    expect($results[1])->toBeInstanceOf(Success::class);
});

test('pool handles different HTTP methods', function() {
    $requests = [
        new HttpRequest('https://httpbin.org/get', 'GET', [], [], []),
        new HttpRequest('https://httpbin.org/post', 'POST', [], ['test' => 'data'], []),
        new HttpRequest('https://httpbin.org/put', 'PUT', [], ['test' => 'data'], []),
    ];

    $results = $this->client->pool($requests);

    expect($results)->toHaveCount(3);
    foreach ($results as $result) {
        expect($result)->toBeInstanceOf(Success::class);
    }
});

test('pool with maxConcurrent parameter', function() {
    $requests = [
        new HttpRequest('https://httpbin.org/get?test=1', 'GET', [], [], []),
        new HttpRequest('https://httpbin.org/get?test=2', 'GET', [], [], []),
        new HttpRequest('https://httpbin.org/get?test=3', 'GET', [], [], []),
    ];

    $results = $this->client->pool($requests, maxConcurrent: 2);

    expect($results)->toHaveCount(3);
    expect($results[0])->toBeInstanceOf(Success::class);
    expect($results[1])->toBeInstanceOf(Success::class);
    expect($results[2])->toBeInstanceOf(Success::class);
});

test('pool handles request failures gracefully', function() {
    $requests = [
        new HttpRequest('https://httpbin.org/get', 'GET', [], [], []),
        new HttpRequest('https://non-existent-domain.invalid', 'GET', [], [], []),
    ];

    $results = $this->client->pool($requests);

    expect($results)->toHaveCount(2);
    expect($results[0])->toBeInstanceOf(Success::class);
    expect($results[1])->toBeInstanceOf(Failure::class); // Invalid domain should fail
});

test('pool with empty requests array', function() {
    $results = $this->client->pool([]);

    expect($results)->toHaveCount(0);
    expect($results)->toBeArray();
});

test('pool with single request', function() {
    $requests = [
        new HttpRequest('https://httpbin.org/get', 'GET', [], [], []),
    ];

    $results = $this->client->pool($requests);

    expect($results)->toHaveCount(1);
    expect($results[0])->toBeInstanceOf(Success::class);
});

test('pool concurrent execution works', function() {
    $requests = [
        new HttpRequest('https://httpbin.org/get?test=1', 'GET', [], [], []),
        new HttpRequest('https://httpbin.org/get?test=2', 'GET', [], [], []),
        new HttpRequest('https://httpbin.org/get?test=3', 'GET', [], [], []),
    ];

    // Test concurrent execution
    $results = $this->client->pool($requests, maxConcurrent: 3);

    expect($results)->toHaveCount(3);
    expect($results[0])->toBeInstanceOf(Success::class);
    expect($results[1])->toBeInstanceOf(Success::class);
    expect($results[2])->toBeInstanceOf(Success::class);
});

test('different drivers can be used for pooling', function() {
    $requests = [
        new HttpRequest('https://httpbin.org/get?driver=guzzle', 'GET', [], [], []),
    ];

    $guzzleClient = HttpClient::using('guzzle');
    $results = $guzzleClient->pool($requests);

    expect($results)->toHaveCount(1);
    expect($results[0])->toBeInstanceOf(Success::class);
});