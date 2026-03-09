<?php declare(strict_types=1);

use Cognesy\HttpPool\Config\HttpPoolConfig;
use Cognesy\HttpPool\Creation\HttpPoolBuilder;
use Cognesy\HttpPool\Drivers\Guzzle\GuzzlePool;
use Cognesy\HttpPool\PendingHttpPool;
use Cognesy\Http\Collections\HttpRequestList;

test('withRequests selects pool handler matching configured driver', function () {
    $pool = (new HttpPoolBuilder())
        ->withConfig(new HttpPoolConfig(driver: 'guzzle'))
        ->create();

    $pendingPool = $pool->withRequests(HttpRequestList::empty());

    $poolHandlerProperty = new ReflectionProperty(PendingHttpPool::class, 'poolHandler');
    $poolHandler = $poolHandlerProperty->getValue($pendingPool);

    expect($poolHandler)->toBeInstanceOf(GuzzlePool::class);
});

test('configured settings are passed to pool handler', function () {
    $pool = (new HttpPoolBuilder())
        ->withConfig(new HttpPoolConfig(driver: 'guzzle', maxConcurrent: 17, poolTimeout: 77))
        ->create();

    $pendingPool = $pool->withRequests(HttpRequestList::empty());

    $poolHandlerProperty = new ReflectionProperty(PendingHttpPool::class, 'poolHandler');
    $poolHandler = $poolHandlerProperty->getValue($pendingPool);
    $configProperty = new ReflectionProperty($poolHandler, 'config');
    $poolConfig = $configProperty->getValue($poolHandler);

    expect($poolConfig->driver)->toBe('guzzle')
        ->and($poolConfig->maxConcurrent)->toBe(17)
        ->and($poolConfig->poolTimeout)->toBe(77);
});
