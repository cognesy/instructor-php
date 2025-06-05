<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\ConfigProviders\StructuredOutputConfigSource;
use Cognesy\Instructor\Contracts\CanProvideStructuredOutputConfig;
use Cognesy\Instructor\Data\StructuredOutputConfig;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

class StructuredOutputConfigBuilder
{
    private ?OutputMode $outputMode = null;
    private ?bool $useObjectReferences = null;
    private ?int $maxRetries = null;
    private ?string $retryPrompt = null;
    private ?array $modePrompts = null;
    private ?string $schemaName = null;
    private ?string $toolName = null;
    private ?string $toolDescription = null;
    private ?string $defaultOutputClass = null;
    private ?array $chatStructure = null;

    private ?CanProvideStructuredOutputConfig $configProvider = null;

    public function __construct(
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
        ?CanProvideStructuredOutputConfig $configProvider = null,
    ) {
        $this->outputMode = $outputMode;
        $this->useObjectReferences = $useObjectReferences;
        $this->maxRetries = $maxRetries;
        $this->retryPrompt = $retryPrompt;
        $this->modePrompts = $modePrompts ?? [];
        $this->schemaName = $schemaName;
        $this->toolName = $toolName;
        $this->toolDescription = $toolDescription;
        $this->chatStructure = $chatStructure ?? [];
        $this->defaultOutputClass = $defaultOutputClass;
        $this->configProvider = StructuredOutputConfigSource::makeWith($configProvider);
    }

    public function withOutputMode(?OutputMode $outputMode) : static
    {
        $this->outputMode = $outputMode;
        return $this;
    }

    public function withMaxRetries(int $maxRetries) : static
    {
        $this->maxRetries = $maxRetries;
        return $this;
    }

    public function withSchemaName(string $schemaName) : static {
        $this->schemaName = $schemaName;
        return $this;
    }

    public function withToolName(string $toolName) : static
    {
        $this->toolName = $toolName;
        return $this;
    }

    public function withToolDescription(string $toolDescription) : static
    {
        $this->toolDescription = $toolDescription;
        return $this;
    }

    public function withUseObjectReferences(bool $useObjectReferences) : static
    {
        $this->useObjectReferences = $useObjectReferences;
        return $this;
    }

    public function withRetryPrompt(string $retryPrompt) : static
    {
        $this->retryPrompt = $retryPrompt;
        return $this;
    }

    public function withModePrompt(OutputMode $mode, string $prompt) : static
    {
        $this->modePrompts[$mode->value] = $prompt;
        return $this;
    }

    public function withModePrompts(array $modePrompts) : static
    {
        $this->modePrompts = $modePrompts;
        return $this;
    }

    public function withChatStructure(array $chatStructure) : static
    {
        $this->chatStructure = $chatStructure;
        return $this;
    }

    public function withDefaultOutputClass(string $defaultOutputClass) : static
    {
        $this->defaultOutputClass = $defaultOutputClass;
        return $this;
    }

//    public function withOverrides(
//        ?OutputMode    $outputMode = null,
//        ?bool          $useObjectReferences = null,
//        ?int           $maxRetries = null,
//        ?string        $retryPrompt = null,
//        ?string        $toolName = null,
//        ?string        $toolDescription = null,
//    ) : static {
//        $this->outputMode = $outputMode ?? $this->outputMode;
//        $this->useObjectReferences = $useObjectReferences ?? $this->useObjectReferences;
//        $this->maxRetries = $maxRetries ?? $this->maxRetries;
//        $this->toolName = $toolName ?? $this->toolName;
//        $this->toolDescription = $toolDescription ?? $this->toolDescription;
//        $this->retryPrompt = $retryPrompt ?? $this->retryPrompt;
//        return $this;
//    }

    public function create() : StructuredOutputConfig {

        return new StructuredOutputConfig(
            outputMode: $this->outputMode,
            useObjectReferences: $this->useObjectReferences,
            maxRetries: $this->maxRetries,
            retryPrompt: $this->retryPrompt,
            modePrompts: $this->modePrompts,
            schemaName: $this->schemaName,
            toolName: $this->toolName,
            toolDescription: $this->toolDescription,
            chatStructure: $this->chatStructure,
            defaultOutputClass: $this->defaultOutputClass,
        );
    }
}