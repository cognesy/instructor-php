<?php

namespace Cognesy\Polyglot\LLM\Config;

use Cognesy\Polyglot\LLM\Contracts\CanProvideLLMConfig;
use Cognesy\Utils\Config\Providers\ConfigResolver;

class LLMConfigResolver extends ConfigResolver implements CanProvideLLMConfig
{
    static public function default() : static {
        return (new static())->tryFrom(fn() => new SettingsLLMConfigProvider());
    }

    static public function defaultWithEmptyFallback(): static
    {
        return (new static())
            ->tryFrom(fn() => new SettingsLLMConfigProvider())
            ->fallbackTo(fn() => new LLMConfig())
            ->allowEmptyFallback(true);
    }

   static public function makeWith(?CanProvideLLMConfig $provider) : static {
        return (new static())
            ->tryFrom($provider)
            ->thenFrom(fn() => new SettingsLLMConfigProvider())
            ->allowEmptyFallback(false);
    }

    public function getConfig(?string $preset = ''): LLMConfig {
        return parent::getConfig($preset);
    }
}
