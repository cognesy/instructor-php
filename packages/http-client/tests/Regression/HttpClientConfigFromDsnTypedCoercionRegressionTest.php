<?php declare(strict_types=1);

use Cognesy\Config\Exceptions\ConfigurationException;
use Cognesy\Http\Config\HttpClientConfig;

it('coerces typed http client config values from dsn', function () {
    $config = HttpClientConfig::fromDsn(
        'driver=symfony,connectTimeout=1,requestTimeout=20,idleTimeout=-1,streamChunkSize=512,maxConcurrent=7,poolTimeout=60,failOnError=true'
    );

    expect($config->driver)->toBe('symfony')
        ->and($config->connectTimeout)->toBe(1)
        ->and($config->requestTimeout)->toBe(20)
        ->and($config->idleTimeout)->toBe(-1)
        ->and($config->streamChunkSize)->toBe(512)
        ->and($config->maxConcurrent)->toBe(7)
        ->and($config->poolTimeout)->toBe(60)
        ->and($config->failOnError)->toBeTrue();
});

it('throws configuration exception for invalid integer dsn values', function () {
    expect(fn() => HttpClientConfig::fromDsn('driver=curl,connectTimeout=abc'))
        ->toThrow(ConfigurationException::class, 'connectTimeout');
});

it('throws configuration exception for invalid boolean dsn values', function () {
    expect(fn() => HttpClientConfig::fromDsn('driver=curl,failOnError=maybe'))
        ->toThrow(ConfigurationException::class, 'failOnError');
});
