<?php declare(strict_types=1);

use Cognesy\Config\Exceptions\ConfigurationException;
use Cognesy\Http\Config\HttpClientConfig;

it('wraps unknown named arguments as configuration exception', function () {
    expect(fn() => HttpClientConfig::fromArray([
        'driver' => 'curl',
        'legacyOption' => 'value',
    ]))->toThrow(ConfigurationException::class);
});
