<?php declare(strict_types=1);

use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Tests\Support\IntegrationTestServer;
use Cognesy\HttpPool\Config\HttpPoolConfig;
use Cognesy\HttpPool\HttpPool;
use Cognesy\HttpPool\PendingHttpPool;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Success;

beforeEach(function () {
    $this->baseUrl = IntegrationTestServer::start();
    $this->pool = HttpPool::default();
});

test('PendingHttpPool can be created with requests', function () {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], []),
    );

    expect($this->pool->withRequests($requests))->toBeInstanceOf(PendingHttpPool::class);
});

test('PendingHttpPool all() executes all requests', function () {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=3', 'GET', [], [], []),
    );

    $results = $this->pool->withRequests($requests)->all();

    expect($results)->toHaveCount(3);
    foreach ($results as $result) {
        expect($result)->toBeInstanceOf(Success::class);
    }
});

test('PendingHttpPool all() respects maxConcurrent parameter', function () {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=1', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=2', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/get?test=3', 'GET', [], [], []),
    );

    $results = $this->pool->withRequests($requests)->all(maxConcurrent: 2);
    $resultArray = $results->all();

    expect($results)->toHaveCount(3)
        ->and($resultArray[0])->toBeInstanceOf(Success::class)
        ->and($resultArray[1])->toBeInstanceOf(Success::class)
        ->and($resultArray[2])->toBeInstanceOf(Success::class);
});

test('PendingHttpPool can be reused', function () {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?test=reuse', 'GET', [], [], []),
    );

    $pendingPool = $this->pool->withRequests($requests);

    expect($pendingPool->all())->toHaveCount(1)
        ->and($pendingPool->all())->toHaveCount(1);
});

test('PendingHttpPool handles failures', function () {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get', 'GET', [], [], []),
        new HttpRequest($this->baseUrl . '/status/500', 'GET', [], [], []),
    );

    $results = $this->pool->withRequests($requests)->all();
    $resultArray = $results->all();

    expect($results)->toHaveCount(2)
        ->and($resultArray[0])->toBeInstanceOf(Success::class)
        ->and($resultArray[1])->toBeInstanceOf(Failure::class);
});

test('PendingHttpPool works with different drivers', function () {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/get?driver=test', 'GET', [], [], []),
    );

    $pendingPool = HttpPool::fromConfig(new HttpPoolConfig(driver: 'guzzle'))->withRequests($requests);
    $results = $pendingPool->all();

    expect($results)->toHaveCount(1)
        ->and($results->all()[0])->toBeInstanceOf(Success::class);
});

register_shutdown_function(static function () {
    IntegrationTestServer::stop();
});
