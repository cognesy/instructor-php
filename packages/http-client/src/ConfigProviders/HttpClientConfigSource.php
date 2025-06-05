<?php

namespace Cognesy\Http\ConfigProviders;

use Cognesy\Http\Contracts\CanProvideHttpClientConfig;
use Cognesy\Http\Data\HttpClientConfig;
use Cognesy\Utils\Config\ConfigProviders\ConfigSource;

class HttpClientConfigSource extends ConfigSource implements CanProvideHttpClientConfig
{
    static public function default() : static {
        return (new static())->tryFrom(fn() => new SettingsHttpClientConfigProvider());
    }

    static public function defaultWithEmptyFallback() : static {
        return (new static())
            ->tryFrom(fn() => new SettingsHttpClientConfigProvider())
            ->fallbackTo(fn() => new HttpClientConfig())
            ->allowEmptyFallback(true);
    }

    static public function makeWith(?CanProvideHttpClientConfig $provider) : static {
        return (new static())
            ->tryFrom($provider)
            ->thenFrom(fn() => new SettingsHttpClientConfigProvider());
    }

    public function getConfig(?string $preset = null): HttpClientConfig {
        return parent::getConfig($preset);
    }
}