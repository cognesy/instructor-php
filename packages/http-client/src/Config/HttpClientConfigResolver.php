<?php

namespace Cognesy\Http\Config;

use Cognesy\Http\Contracts\CanProvideHttpClientConfig;
use Cognesy\Utils\Config\Providers\ConfigResolver;

class HttpClientConfigResolver extends ConfigResolver implements CanProvideHttpClientConfig
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