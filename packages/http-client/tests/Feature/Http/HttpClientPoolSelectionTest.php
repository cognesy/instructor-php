<?php

use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Guzzle\GuzzlePool;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\HttpClient;
use Cognesy\Http\PendingHttpPool;

test('withPool selects pool handler matching active driver', function() {
    $client = HttpClient::using('guzzle');
    $pendingPool = $client->withPool(HttpRequestList::empty());

    $poolHandlerProperty = new \ReflectionProperty(PendingHttpPool::class, 'poolHandler');
    $poolHandler = $poolHandlerProperty->getValue($pendingPool);

    expect($poolHandler)->toBeInstanceOf(GuzzlePool::class);
});

test('withPool passes configured settings to pool handler', function() {
    $config = new HttpClientConfig(driver: 'guzzle', maxConcurrent: 17, poolTimeout: 77);
    $client = (new HttpClientBuilder())
        ->withConfig($config)
        ->create();
    $pendingPool = $client->withPool(HttpRequestList::empty());

    $poolHandlerProperty = new \ReflectionProperty(PendingHttpPool::class, 'poolHandler');
    $poolHandler = $poolHandlerProperty->getValue($pendingPool);

    $configProperty = new \ReflectionProperty($poolHandler, 'config');
    $poolConfig = $configProperty->getValue($poolHandler);

    expect($poolConfig->driver)->toBe('guzzle');
    expect($poolConfig->maxConcurrent)->toBe(17);
    expect($poolConfig->poolTimeout)->toBe(77);
});

test('pooling is rejected for external non-pooling drivers', function() {
    $client = (new HttpClientBuilder())
        ->withDriver(new MockHttpDriver())
        ->create();

    expect(fn() => $client->withPool(HttpRequestList::empty()))
        ->toThrow(\InvalidArgumentException::class, 'does not support request pooling');
});
