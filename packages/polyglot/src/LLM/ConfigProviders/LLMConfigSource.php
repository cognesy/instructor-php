<?php

namespace Cognesy\Polyglot\LLM\ConfigProviders;

use Cognesy\Polyglot\LLM\Contracts\CanProvideLLMConfig;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Utils\Config\ConfigProviders\ConfigSource;

class LLMConfigSource extends ConfigSource implements CanProvideLLMConfig
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
