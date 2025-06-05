<?php

namespace Cognesy\Instructor\ConfigProviders;

use Cognesy\Instructor\Contracts\CanProvideStructuredOutputConfig;
use Cognesy\Instructor\Data\StructuredOutputConfig;
use Cognesy\Utils\Config\ConfigProviders\ConfigSource;

class StructuredOutputConfigSource extends ConfigSource implements CanProvideStructuredOutputConfig
{
    static public function default() : static {
        return (new static())->tryFrom(fn() => new SettingsStructuredOutputConfigProvider());
    }

    static public function defaultWithEmptyFallback() : static {
        return (new static())
            ->tryFrom(fn() => new SettingsStructuredOutputConfigProvider())
            ->fallbackTo(fn() => new StructuredOutputConfig())
            ->allowEmptyFallback(true);
    }

    static public function makeWith(?CanProvideStructuredOutputConfig $provider) : static {
        return (new static())
            ->tryFrom($provider)
            ->thenFrom(fn() => new SettingsStructuredOutputConfigProvider())
            ->allowEmptyFallback(false);
    }

    public function getConfig(?string $preset = null): StructuredOutputConfig {
        return parent::getConfig($preset);
    }
}
