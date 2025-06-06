<?php

namespace Cognesy\Template\Config;

use Cognesy\Template\Enums\TemplateEngineType;
use Cognesy\Utils\Config\Providers\SettingsConfigProvider;

/**
 * @extends SettingsConfigProvider<TemplateEngineConfig>
 */
class SettingsTemplateConfigProvider extends SettingsConfigProvider
{
    protected function getGroup(): string {
        return 'prompt';
    }

    protected function createConfig(string $preset): TemplateEngineConfig {
        return new TemplateEngineConfig(
            templateEngine: TemplateEngineType::from($this->getSetting("presets.$preset.templateEngine", '')),
            resourcePath: $this->getSetting("presets.$preset.resourcePath", 'prompts'),
            cachePath: $this->getSetting("presets.$preset.cachePath", '/tmp'),
            extension: $this->getSetting("presets.$preset.extension", 'twig'),
            metadata: $this->getSetting("presets.$preset.metadata", []),
        );
    }
}