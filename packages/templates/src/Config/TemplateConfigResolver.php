<?php

namespace Cognesy\Template\Config;

use Cognesy\Template\Contracts\CanProvideTemplateConfig;
use Cognesy\Utils\Config\Providers\ConfigResolver;

class TemplateConfigResolver extends ConfigResolver implements CanProvideTemplateConfig
{
    static public function default() : static {
        return (new static())->tryFrom(fn() => new SettingsTemplateConfigProvider());
    }

    static public function defaultWithEmptyFallback() : static {
        return (new static())
            ->tryFrom(fn() => new SettingsTemplateConfigProvider())
            ->fallbackTo(fn() => new TemplateEngineConfig())
            ->allowEmptyFallback(true);
    }

    static public function makeWith(?CanProvideTemplateConfig $provider) : static {
        return (new static())
            ->tryFrom($provider)
            ->thenFrom(fn() => new SettingsTemplateConfigProvider())
            ->allowEmptyFallback(false);
    }

    public function getConfig(?string $preset = null): TemplateEngineConfig {
        return parent::getConfig($preset);
    }
}
