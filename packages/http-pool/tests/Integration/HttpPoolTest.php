<?php declare(strict_types=1);

use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Tests\Support\IntegrationTestServer;
use Cognesy\HttpPool\Config\HttpPoolConfig;
use Cognesy\HttpPool\Creation\HttpPoolBuilder;
use Cognesy\HttpPool\Drivers\Curl\Pool\CurlPool;
use Cognesy\HttpPool\Drivers\Guzzle\GuzzlePool;
use Cognesy\HttpPool\HttpPool;
use Cognesy\HttpPool\PendingHttpPool;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Success;

beforeEach(function () {
    $this->baseUrl = IntegrationTestServer::start();
    $this->pool = HttpPool::default();
});

test('pool executes requests immediately', function () {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], []),
    );

    $results = $this->pool->pool($requests, maxConcurrent: 2);
    $resultArray = $results->all();

    expect($results)->toHaveCount(2)
        ->and($resultArray[0])->toBeInstanceOf(Success::class)
        ->and($resultArray[1])->toBeInstanceOf(Success::class);
});

test('withRequests returns PendingHttpPool', function () {
    $pendingPool = $this->pool->withRequests(HttpRequestList::empty());

    expect($pendingPool)->toBeInstanceOf(PendingHttpPool::class);
});

test('pending pool executes deferred requests', function () {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], []),
    );

    $results = $this->pool->withRequests($requests)->all(maxConcurrent: 2);
    $resultArray = $results->all();

    expect($results)->toHaveCount(2)
        ->and($resultArray[0])->toBeInstanceOf(Success::class)
        ->and($resultArray[1])->toBeInstanceOf(Success::class);
});

test('pool handles mixed methods and failures', function () {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/post', 'POST', [], ['test' => 'data'], []),
        new HttpRequest($this->baseUrl . '/status/500', 'GET', [], [], []),
    );

    $results = $this->pool->pool($requests, maxConcurrent: 2);
    $resultArray = $results->all();

    expect($results)->toHaveCount(3)
        ->and($resultArray[0])->toBeInstanceOf(Success::class)
        ->and($resultArray[1])->toBeInstanceOf(Success::class)
        ->and($resultArray[2])->toBeInstanceOf(Failure::class);
});

test('pool handles empty requests', function () {
    expect($this->pool->pool(HttpRequestList::empty()))->toHaveCount(0);
});

test('different drivers can be used for pooling', function () {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?driver=guzzle', 'GET', [], [], []),
    );

    $guzzlePool = HttpPool::fromConfig(new HttpPoolConfig(driver: 'guzzle'));
    $results = $guzzlePool->pool($requests);

    expect($results)->toHaveCount(1)
        ->and($results->all()[0])->toBeInstanceOf(Success::class);
});

test('pool selects matching handler for configured driver', function () {
    $curlPendingPool = HttpPool::default()->withRequests(HttpRequestList::empty());
    $guzzlePendingPool = HttpPool::fromConfig(new HttpPoolConfig(driver: 'guzzle'))->withRequests(HttpRequestList::empty());

    $poolHandlerProperty = new ReflectionProperty(PendingHttpPool::class, 'poolHandler');
    $curlPoolHandler = $poolHandlerProperty->getValue($curlPendingPool);
    $guzzlePoolHandler = $poolHandlerProperty->getValue($guzzlePendingPool);

    expect($curlPoolHandler)->toBeInstanceOf(CurlPool::class)
        ->and($guzzlePoolHandler)->toBeInstanceOf(GuzzlePool::class);
});

register_shutdown_function(static function () {
    IntegrationTestServer::stop();
});
