<?php

namespace Cognesy\Instructor;

use Cognesy\Instructor\Contracts\CanProvideStructuredOutputConfig;
use Cognesy\Instructor\Data\StructuredOutputConfig;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Config\Settings;
use Cognesy\Utils\Result\Result;
use InvalidArgumentException;

class SettingsStructuredOutputConfigProvider implements CanProvideStructuredOutputConfig
{
    public function getConfig(?string $preset = '') : StructuredOutputConfig {
        if (empty($preset)) {
            $result = Result::try(fn() => Settings::get('structured', 'defaultPreset', ''));
            $preset = $result->isSuccess() ? $result->unwrap() : '';
            if (empty($preset)) {
                return new StructuredOutputConfig();
            }
        }

        if (!Settings::has('structured', "presets.$preset")) {
            throw new InvalidArgumentException("Unknown structured output config preset: $preset");
        }

        return new StructuredOutputConfig(
            outputMode: OutputMode::from(Settings::get('structured', "presets.$preset.defaultMode", OutputMode::MdJson->value)),
            useObjectReferences: Settings::get('structured', "presets.$preset.useObjectReferences", null),
            maxRetries: Settings::get('structured', "presets.$preset.maxRetries", 0),
            retryPrompt: Settings::get('structured', "presets.$preset.defaultRetryPrompt", ''),
            modePrompts: [
                OutputMode::MdJson->value => Settings::get('structured', "presets.$preset.defaultMdJsonPrompt", ''),
                OutputMode::Json->value => Settings::get('structured', "presets.$preset.defaultJsonPrompt", ''),
                OutputMode::JsonSchema->value => Settings::get('structured', "presets.$preset.defaultJsonSchemaPrompt", ''),
                OutputMode::Tools->value => Settings::get('structured', "presets.$preset.defaultToolsPrompt", ''),
            ],
            schemaName: Settings::get('structured', "presets.$preset.defaultSchemaName", ''),
            toolName: Settings::get('structured', "presets.$preset.defaultToolName", ''),
            toolDescription: Settings::get('structured', "presets.$preset.defaultToolDescription", ''),
            chatStructure: Settings::get('structured', "presets.$preset.defaultChatStructure", []),
            defaultOutputClass: Settings::get('structured', "presets.$preset.defaultOutputClass", ''),
        );
    }
}