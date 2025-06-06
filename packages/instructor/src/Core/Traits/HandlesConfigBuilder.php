<?php

namespace Cognesy\Instructor\Core\Traits;

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Contracts\CanProvideStructuredOutputConfig;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

trait HandlesConfigBuilder
{
    public function withOutputMode(?OutputMode $outputMode): static {
        $this->config->withOutputMode($outputMode);
        return $this;
    }

    public function withMaxRetries(int $maxRetries): static {
        $this->config->withMaxRetries($maxRetries);
        return $this;
    }

    public function withSchemaName(string $schemaName): static {
        $this->config->withSchemaName($schemaName);
        return $this;
    }

    public function withToolName(string $toolName): static {
        $this->config->withToolName($toolName);
        return $this;
    }

    public function withToolDescription(string $toolDescription): static {
        $this->config->withToolDescription($toolDescription);
        return $this;
    }

    public function withRetryPrompt(string $retryPrompt): static {
        $this->config->withRetryPrompt($retryPrompt);
        return $this;
    }

    public function withConfig(StructuredOutputConfig $config): static {
        $this->config = $config;
        return $this;
    }

    public function withConfigPreset(string $preset): static {
        $this->config = $this->configProvider->getConfig($preset);
        return $this;
    }

    public function withConfigProvider(CanProvideStructuredOutputConfig $configProvider): static {
        $this->configProvider = $configProvider;
        $this->config = $this->configProvider->getConfig();
        return $this;
    }

    public function withObjectReferences(bool $useObjectReferences): static {
        $this->config->withUseObjectReferences($useObjectReferences);
        return $this;
    }
}