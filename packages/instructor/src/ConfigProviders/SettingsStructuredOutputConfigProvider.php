<?php

namespace Cognesy\Instructor\ConfigProviders;

use Cognesy\Instructor\Data\StructuredOutputConfig;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Config\ConfigProviders\SettingsConfigProvider;

/**
 * @extends SettingsConfigProvider<StructuredOutputConfig>
 */
class SettingsStructuredOutputConfigProvider extends SettingsConfigProvider {
    protected function getGroup(): string {
        return 'structured';
    }

    protected function createConfig(string $preset): StructuredOutputConfig {
        return new StructuredOutputConfig(
            outputMode: OutputMode::fromText($this->getSetting("presets.$preset.defaultMode", OutputMode::Tools->value)),
            useObjectReferences: $this->getSetting("presets.$preset.useObjectReferences", false),
            maxRetries: $this->getSetting("presets.$preset.maxRetries", 0),
            retryPrompt: $this->getSetting("presets.$preset.defaultRetryPrompt", ''),
            modePrompts: [
                OutputMode::MdJson->value => $this->getSetting("presets.$preset.defaultMdJsonPrompt", ''),
                OutputMode::Json->value => $this->getSetting("presets.$preset.defaultJsonPrompt", ''),
                OutputMode::JsonSchema->value => $this->getSetting("presets.$preset.defaultJsonSchemaPrompt", ''),
                OutputMode::Tools->value => $this->getSetting("presets.$preset.defaultToolsPrompt", ''),
            ],
            schemaName: $this->getSetting("presets.$preset.defaultSchemaName", ''),
            toolName: $this->getSetting("presets.$preset.defaultToolName", ''),
            toolDescription: $this->getSetting("presets.$preset.defaultToolDescription", ''),
            chatStructure: $this->getSetting("presets.$preset.defaultChatStructure", []),
            defaultOutputClass: $this->getSetting("presets.$preset.defaultOutputClass", ''),
        );
    }
}
