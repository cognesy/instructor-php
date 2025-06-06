<?php

namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Core\StructuredOutputConfigBuilder;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Config\Contracts\CanProvideConfig;

trait HandlesConfigBuilder
{
    private StructuredOutputConfigBuilder $configBuilder;

    public function withMaxRetries(int $maxRetries) : self {
        $this->configBuilder->withMaxRetries($maxRetries);
        return $this;
    }

    public function withOutputMode(OutputMode $outputMode): static {
        $this->configBuilder->withOutputMode($outputMode);
        return $this;
    }

    public function withSchemaName(string $schemaName): static {
        $this->configBuilder->withSchemaName($schemaName);
        return $this;
    }

    public function withToolName(string $toolName): static {
        $this->configBuilder->withToolName($toolName);
        return $this;
    }

    public function withToolDescription(string $toolDescription): static {
        $this->configBuilder->withToolDescription($toolDescription);
        return $this;
    }

    public function withRetryPrompt(string $retryPrompt): static {
        $this->configBuilder->withRetryPrompt($retryPrompt);
        return $this;
    }

    public function withConfig(StructuredOutputConfig $config): static {
        $this->configBuilder->withConfig($config);
        return $this;
    }

    public function withConfigPreset(string $preset): static {
        $this->configBuilder->withConfigPreset($preset);
        return $this;
    }

    public function withConfigProvider(CanProvideConfig $configProvider): static {
        $this->configBuilder->withConfigProvider($configProvider);
        return $this;
    }

    public function withObjectReferences(bool $useObjectReferences): static {
        $this->configBuilder->withUseObjectReferences($useObjectReferences);
        return $this;
    }
}