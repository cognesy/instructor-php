<?php declare(strict_types=1);

namespace Cognesy\Instructor\Creation;

use Cognesy\Config\ConfigPresets;
use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;

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
    private ?bool $defaultToStdClass = null;
    private ?string $deserializationErrorPrompt = null;
    private ?bool $throwOnTransformationFailure = null;
    private ?ResponseCachePolicy $responseCachePolicy = null;

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

    public function withDefaultToStdClass(bool $defaultToStdClass) : self {
        $this->defaultToStdClass = $defaultToStdClass;
        return $this;
    }

    public function withDeserializationErrorPrompt(string $deserializationErrorPrompt) : self {
        $this->deserializationErrorPrompt = $deserializationErrorPrompt;
        return $this;
    }

    public function withThrowOnTransformationFailure(bool $throwOnTransformationFailure) : self {
        $this->throwOnTransformationFailure = $throwOnTransformationFailure;
        return $this;
    }

    public function withResponseCachePolicy(ResponseCachePolicy $responseCachePolicy): self {
        $this->responseCachePolicy = $responseCachePolicy;
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
        ?string $defaultOutputClass = null,
        ?ResponseCachePolicy $responseCachePolicy = null
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
        $this->responseCachePolicy = $responseCachePolicy ?? $this->responseCachePolicy;
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
        $data = $this->presets->getOrDefault($this->configPreset);
        $defaults = StructuredOutputConfig::fromArray($data);

        if ($this->explicitConfig) {
            $defaults = $defaults->withOverrides($this->explicitConfig->toArray());
        }

        $config = new StructuredOutputConfig(
            outputMode: $this->outputMode ?? $defaults->outputMode(),
            outputClass: $this->defaultOutputClass ?? $defaults->outputClass(),
            useObjectReferences: $this->useObjectReferences ?? $defaults->useObjectReferences(),
            maxRetries: $this->maxRetries ?? $defaults->maxRetries(),
            schemaName: $this->schemaName ?? $defaults->schemaName(),
            schemaDescription: $this->schemaDescription ?? $defaults->schemaDescription(),
            toolName: $this->toolName ?? $defaults->toolName(),
            toolDescription: $this->toolDescription ?? $defaults->toolDescription(),
            modePrompts: array_merge($defaults->modePrompts(), $this->modePrompts ?? []),
            retryPrompt: $this->retryPrompt ?? $defaults->retryPrompt(),
            chatStructure: array_merge($defaults->chatStructure(), $this->chatStructure ?? []),
            defaultToStdClass: $this->defaultToStdClass ?? $defaults->defaultToStdClass(),
            deserializationErrorPrompt: $this->deserializationErrorPrompt ?? $defaults->deserializationErrorPrompt(),
            throwOnTransformationFailure: $this->throwOnTransformationFailure ?? $defaults->throwOnTransformationFailure(),
            responseCachePolicy: $this->responseCachePolicy ?? $defaults->responseCachePolicy(),
        );
        return $config;
    }
}
