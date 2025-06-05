<?php

namespace Cognesy\Http\ConfigProviders;

use Cognesy\Http\Contracts\CanProvideDebugConfig;
use Cognesy\Http\Debug\DebugConfig;
use Cognesy\Utils\Config\ConfigProviders\ConfigSource;

class DebugConfigSource extends ConfigSource implements CanProvideDebugConfig
{
    static public function default() : static {
        return (new static())->tryFrom(fn() => new SettingsDebugConfigProvider());
    }

    static public function defaultWithEmptyFallback() : static {
        return (new static())
            ->tryFrom(fn() => new SettingsDebugConfigProvider())
            ->fallbackTo(fn() => new DebugConfig())
            ->allowEmptyFallback(true);
    }

    static public function makeWith(?CanProvideDebugConfig $provider) : static {
        return (new static())
            ->tryFrom($provider)
            ->thenFrom(fn() => new SettingsDebugConfigProvider());
    }

    public function getConfig(?string $preset = null): DebugConfig {
        return parent::getConfig($preset);
    }
}