<?php

namespace Cognesy\Template\ConfigProviders;

use Cognesy\Template\Data\TemplateEngineConfig;
use Cognesy\Template\Enums\TemplateEngineType;
use Cognesy\Utils\Config\ConfigProviders\SettingsConfigProvider;

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