<?php

namespace Cognesy\Instructor\Data\Traits\StructuredOutputConfig;

use Cognesy\Polyglot\LLM\Enums\OutputMode;

trait HandlesMutators
{
    // MUTATORS ///////////////////////////////////////////////////////

    public function withOutputMode(OutputMode $outputMode) : static
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

    public function withOverrides(
        ?OutputMode    $outputMode = null,
        ?bool          $useObjectReferences = null,
        ?int           $maxRetries = null,
        ?string        $retryPrompt = null,
        ?string        $toolName = null,
        ?string        $toolDescription = null,
    ) : static {
        $this->outputMode = $outputMode ?? $this->outputMode;
        $this->useObjectReferences = $useObjectReferences ?? $this->useObjectReferences;
        $this->maxRetries = $maxRetries ?? $this->maxRetries;
        $this->toolName = $toolName ?? $this->toolName;
        $this->toolDescription = $toolDescription ?? $this->toolDescription;
        $this->retryPrompt = $retryPrompt ?? $this->retryPrompt;
        return $this;
    }
}