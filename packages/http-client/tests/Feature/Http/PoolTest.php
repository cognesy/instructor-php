<?php

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Guzzle\GuzzleDriver;
use Cognesy\Http\Drivers\Laravel\LaravelDriver;
use Cognesy\Http\Drivers\Symfony\SymfonyDriver;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Success;

it('tests pool method for GuzzleDriver with failOnError=true', function () {
    $config = HttpClientConfig::fromArray([
        'httpClientDriver' => 'guzzle',
        'maxConcurrent' => 5,
        'requestTimeout' => 1,
        'connectTimeout' => 1,
        'idleTimeout' => 0,
        'failOnError' => true,
    ]);

    $driver = new GuzzleDriver($config);
    $requests = [
        new HttpRequest('https://example.com', 'GET', [], [], []),
        new HttpRequest('https://invalid-domain-that-does-not-exist.com', 'GET', [], [], []),
    ];

    expect(fn() => $driver->pool($requests))
        ->toThrow(Exception::class);
})->skip('Skipped until request pooling is refactored');

it('tests pool method for GuzzleDriver with failOnError=false', function () {
    $config = HttpClientConfig::fromArray([
        'httpClientDriver' => 'guzzle',
        'maxConcurrent' => 5,
        'requestTimeout' => 1,
        'connectTimeout' => 1,
        'idleTimeout' => 0,
        'failOnError' => false,
    ]);

    $driver = new GuzzleDriver($config);
    $requests = [
        new HttpRequest('https://example.com', 'GET', [], [], []),
        new HttpRequest('https://invalid-domain-that-does-not-exist.com', 'GET', [], [], []),
    ];

    $responses = $driver->pool($requests);

    expect($responses)->toBeArray();
    expect(count($responses))->toBe(2);
    expect($responses[0])->toBeInstanceOf(Success::class);
    expect($responses[1])->toBeInstanceOf(Failure::class);
})->skip('Skipped until request pooling is refactored');

it('tests pool method for LaravelDriver with failOnError=true', function () {
    $config = HttpClientConfig::fromArray([
        'httpClientDriver' => 'laravel',
        'maxConcurrent' => 5,
        'requestTimeout' => 1,
        'connectTimeout' => 1,
        'idleTimeout' => 0,
        'failOnError' => true,
    ]);

    $driver = new LaravelDriver($config);
    $requests = [
        new HttpRequest('https://example.com', 'GET', [], [], []),
        new HttpRequest('https://invalid-domain-that-does-not-exist.com', 'GET', [], [], []),
    ];

    expect(fn() => $driver->pool($requests))
        ->toThrow(Exception::class);
})->skip('Skipped until request pooling is refactored');

it('tests pool method for LaravelDriver with failOnError=false', function () {
    $config = HttpClientConfig::fromArray([
        'httpClientDriver' => 'laravel',
        'maxConcurrent' => 5,
        'requestTimeout' => 1,
        'connectTimeout' => 1,
        'idleTimeout' => 0,
        'failOnError' => false,
    ]);

    $driver = new LaravelDriver($config);
    $requests = [
        new HttpRequest('https://example.com', 'GET', [], [], []),
        new HttpRequest('https://invalid-domain-that-does-not-exist.com', 'GET', [], [], []),
    ];

    $responses = $driver->pool($requests);

    expect($responses)->toBeArray();
    expect(count($responses))->toBe(2);
    expect($responses[0])->toBeInstanceOf(Success::class);
    expect($responses[1])->toBeInstanceOf(Failure::class);
})->skip('Skipped until request pooling is refactored');

it('tests pool method for SymfonyDriver with failOnError=true', function () {
    $config = HttpClientConfig::fromArray([
        'httpClientDriver' => 'symfony',
        'maxConcurrent' => 5,
        'requestTimeout' => 1,
        'connectTimeout' => 1,
        'idleTimeout' => 0,
        'failOnError' => true,
    ]);

    $driver = new SymfonyDriver(config: $config);
    $requests = [
        new HttpRequest('https://example.com', 'GET', [], [], []),
        new HttpRequest('https://invalid-domain-that-does-not-exist.com', 'GET', [], [], []),
    ];

    expect(fn() => $driver->pool($requests))
        ->toThrow(Exception::class);
})->skip('Skipped until request pooling is refactored');

it('tests pool method for SymfonyDriver with failOnError=false', function () {
    $config = HttpClientConfig::fromArray([
        'httpClientDriver' => 'symfony',
        'maxConcurrent' => 5,
        'requestTimeout' => 1,
        'connectTimeout' => 1,
        'idleTimeout' => 0,
        'failOnError' => false,
    ]);

    $driver = new SymfonyDriver(config: $config);
    $requests = [
        new HttpRequest('https://example.com', 'GET', [], [], []),
        new HttpRequest('https://invalid-domain-that-does-not-exist.com', 'GET', [], [], []),
    ];

    $responses = $driver->pool($requests);

    expect($responses)->toBeArray();
    expect(count($responses))->toBe(2);
    expect($responses[0])->toBeInstanceOf(Success::class);
    expect($responses[1])->toBeInstanceOf(Failure::class);
})->skip('Skipped until request pooling is refactored');
