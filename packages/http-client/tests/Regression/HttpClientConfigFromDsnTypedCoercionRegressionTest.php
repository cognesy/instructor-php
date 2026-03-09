<?php declare(strict_types=1);

use Cognesy\Http\Config\HttpClientConfig;

it('coerces typed http client config values from dsn', function () {
    $config = HttpClientConfig::fromDsn(
        'driver=symfony,connectTimeout=1,requestTimeout=20,idleTimeout=-1,streamChunkSize=512,streamHeaderTimeout=4,failOnError=true'
    );

    expect($config->driver)->toBe('symfony')
        ->and($config->connectTimeout)->toBe(1)
        ->and($config->requestTimeout)->toBe(20)
        ->and($config->idleTimeout)->toBe(-1)
        ->and($config->streamChunkSize)->toBe(512)
        ->and($config->streamHeaderTimeout)->toBe(4)
        ->and($config->failOnError)->toBeTrue();
});

it('throws configuration exception for invalid integer dsn values', function () {
    expect(fn() => HttpClientConfig::fromDsn('driver=curl,connectTimeout=abc'))
        ->toThrow(\InvalidArgumentException::class, 'connectTimeout');
});

it('throws configuration exception for invalid boolean dsn values', function () {
    expect(fn() => HttpClientConfig::fromDsn('driver=curl,failOnError=maybe'))
        ->toThrow(\InvalidArgumentException::class, 'failOnError');
});
