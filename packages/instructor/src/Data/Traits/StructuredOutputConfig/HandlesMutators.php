<?php

namespace Cognesy\Instructor\Data\Traits\StructuredOutputConfig;

use Cognesy\Polyglot\Inference\Enums\OutputMode;

trait HandlesMutators
{
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
        $this->outputClass = $defaultOutputClass;
        return $this;
    }
}