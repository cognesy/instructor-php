<?php declare(strict_types=1);

use Cognesy\HttpPool\Config\HttpPoolConfig;

it('coerces typed http pool config values from dsn', function () {
    $config = HttpPoolConfig::fromDsn(
        'driver=guzzle,connectTimeout=1,requestTimeout=20,idleTimeout=-1,streamChunkSize=512,maxConcurrent=7,poolTimeout=60,failOnError=true'
    );

    expect($config->driver)->toBe('guzzle')
        ->and($config->connectTimeout)->toBe(1)
        ->and($config->requestTimeout)->toBe(20)
        ->and($config->idleTimeout)->toBe(-1)
        ->and($config->streamChunkSize)->toBe(512)
        ->and($config->maxConcurrent)->toBe(7)
        ->and($config->poolTimeout)->toBe(60)
        ->and($config->failOnError)->toBeTrue();
});

it('throws configuration exception for invalid integer dsn values', function () {
    expect(fn() => HttpPoolConfig::fromDsn('driver=curl,connectTimeout=abc'))
        ->toThrow(InvalidArgumentException::class, 'connectTimeout');
});

it('throws configuration exception for invalid boolean dsn values', function () {
    expect(fn() => HttpPoolConfig::fromDsn('driver=curl,failOnError=maybe'))
        ->toThrow(InvalidArgumentException::class, 'failOnError');
});
