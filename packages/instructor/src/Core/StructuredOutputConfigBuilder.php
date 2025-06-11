<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Config\ConfigPresets;
use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

class StructuredOutputConfigBuilder
{
    private ?OutputMode $outputMode;
    private ?bool $useObjectReferences;
    private ?int $maxRetries;
    private ?string $retryPrompt;
    private ?array $modePrompts;
    private ?string $schemaName;
    private ?string $schemaDescription;
    private ?string $toolName;
    private ?string $toolDescription;
    private ?string $defaultOutputClass;
    private ?array $chatStructure;

    private ?string $configPreset = null;
    private ?StructuredOutputConfig $explicitConfig = null;
    private ConfigPresets $presets;

    public function __construct(
        ?OutputMode       $outputMode = null,
        ?bool             $useObjectReferences = null,
        ?int              $maxRetries = null,
        ?string           $retryPrompt = null,
        ?array            $modePrompts = null,
        ?string           $schemaName = null,
        ?string           $schemaDescription = null,
        ?string           $toolName = null,
        ?string           $toolDescription = null,
        ?array            $chatStructure = null,
        ?string           $defaultOutputClass = null,
        ?CanProvideConfig $configProvider = null,
    ) {
        $this->outputMode = $outputMode;
        $this->useObjectReferences = $useObjectReferences;
        $this->maxRetries = $maxRetries;
        $this->retryPrompt = $retryPrompt;
        $this->modePrompts = $modePrompts ?? [];
        $this->schemaName = $schemaName;
        $this->schemaDescription = $schemaDescription;
        $this->toolName = $toolName;
        $this->toolDescription = $toolDescription;
        $this->chatStructure = $chatStructure ?? [];
        $this->defaultOutputClass = $defaultOutputClass;
        $this->presets = ConfigPresets::using($configProvider)->for(StructuredOutputConfig::group());
    }

    public function withConfigProvider(CanProvideConfig $configProvider) : self {
        $this->presets = $this->presets->withConfigProvider($configProvider);
        return $this;
    }

    public function withOutputMode(?OutputMode $outputMode) : static {
        $this->outputMode = $outputMode;
        return $this;
    }

    public function withMaxRetries(int $maxRetries) : static {
        $this->maxRetries = $maxRetries;
        return $this;
    }

    public function withSchemaName(string $schemaName) : static {
        $this->schemaName = $schemaName;
        return $this;
    }

    public function withSchemaDescription(string $schemaDescription) : static {
        $this->schemaDescription = $schemaDescription;
        return $this;
    }

    public function withToolName(string $toolName) : static {
        $this->toolName = $toolName;
        return $this;
    }

    public function withToolDescription(string $toolDescription) : static {
        $this->toolDescription = $toolDescription;
        return $this;
    }

    public function withUseObjectReferences(bool $useObjectReferences) : static {
        $this->useObjectReferences = $useObjectReferences;
        return $this;
    }

    public function withRetryPrompt(string $retryPrompt) : static {
        $this->retryPrompt = $retryPrompt;
        return $this;
    }

    public function withModePrompt(OutputMode $mode, string $prompt) : static {
        $this->modePrompts[$mode->value] = $prompt;
        return $this;
    }

    public function withModePrompts(array $modePrompts) : static {
        $this->modePrompts = $modePrompts;
        return $this;
    }

    public function withChatStructure(array $chatStructure) : static {
        $this->chatStructure = $chatStructure;
        return $this;
    }

    public function withDefaultOutputClass(string $defaultOutputClass) : static {
        $this->defaultOutputClass = $defaultOutputClass;
        return $this;
    }

    public function withConfigPreset(string $preset) : self {
        $this->configPreset = $preset;
        return $this;
    }

    public function with(
        ?OutputMode $outputMode = null,
        ?bool $useObjectReferences = null,
        ?int $maxRetries = null,
        ?string $retryPrompt = null,
        ?array $modePrompts = null,
        ?string $schemaName = null,
        ?string $toolName = null,
        ?string $toolDescription = null,
        ?array $chatStructure = null,
        ?string $defaultOutputClass = null
    ) : self {
        $this->outputMode = $outputMode ?? $this->outputMode;
        $this->useObjectReferences = $useObjectReferences ?? $this->useObjectReferences;
        $this->maxRetries = $maxRetries ?? $this->maxRetries;
        $this->retryPrompt = $retryPrompt ?? $this->retryPrompt;
        $this->modePrompts = $modePrompts ?? $this->modePrompts;
        $this->schemaName = $schemaName ?? $this->schemaName;
        $this->toolName = $toolName ?? $this->toolName;
        $this->toolDescription = $toolDescription ?? $this->toolDescription;
        $this->chatStructure = $chatStructure ?? $this->chatStructure;
        $this->defaultOutputClass = $defaultOutputClass ?? $this->defaultOutputClass;
        return $this;
    }

    public function withPreset(string $preset) : self {
        $this->configPreset = $preset;
        return $this;
    }

    public function withConfig(StructuredOutputConfig $config) : self {
        $this->explicitConfig = $config;
        return $this;
    }

    public function create() : StructuredOutputConfig {
        if ($this->configPreset) {
            $data = $this->presets->get($this->configPreset);
            if (!empty($data)) {
                $this->applyConfig(StructuredOutputConfig::fromArray($data));
            }
        }

        if ($this->explicitConfig) {
            $this->applyConfig($this->explicitConfig);
        }

        $config = new StructuredOutputConfig(
            outputMode: $this->outputMode ?: OutputMode::Tools,
            useObjectReferences: $this->useObjectReferences ?? false,
            maxRetries: $this->maxRetries ?? -1,
            retryPrompt: $this->retryPrompt ?: '',
            modePrompts: $this->modePrompts ?: [],
            schemaName: $this->schemaName ?: '',
            schemaDescription: $this->schemaDescription ?: '',
            toolName: $this->toolName ?: '',
            toolDescription: $this->toolDescription ?: '',
            chatStructure: $this->chatStructure ?: [],
            defaultOutputClass: $this->defaultOutputClass ?: '',
        );

        return $config;
    }

    private function applyConfig(StructuredOutputConfig $config) : self {
        $this->outputMode = $config->outputMode() ?: $this->outputMode;
        $this->useObjectReferences = $config->useObjectReferences() ?: $this->useObjectReferences;
        $this->maxRetries = ($config->maxRetries() > 0) ?: $this->maxRetries;
        $this->retryPrompt = $config->retryPrompt() ?: $this->retryPrompt;
        $this->modePrompts = $config->modePrompts() ?: $this->modePrompts;
        $this->schemaName = $config->schemaName() ?: $this->schemaName;
        $this->schemaDescription = $config->schemaDescription() ?: $this->schemaDescription;
        $this->toolName = $config->toolName() ?: $this->toolName;
        $this->toolDescription = $config->toolDescription() ?: $this->toolDescription;
        $this->chatStructure = $config->chatStructure() ?: $this->chatStructure;
        $this->defaultOutputClass = $config->defaultOutputClass() ?: $this->defaultOutputClass;

        return $this;
    }
}