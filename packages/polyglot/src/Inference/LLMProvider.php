<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference;

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanAcceptLLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanResolveLLMConfig;
use Cognesy\Polyglot\Inference\Contracts\HasExplicitInferenceDriver;

final class LLMProvider implements CanResolveLLMConfig, HasExplicitInferenceDriver, CanAcceptLLMConfig
{
    private function __construct(
        private readonly LLMConfig $config,
        private readonly ?CanProcessInferenceRequest $explicitDriver = null,
    ) {}

    // FACTORIES /////////////////////////////////////////////////////////////

    public static function new(?LLMConfig $config = null): self {
        return new self(config: $config ?? LLMConfig::fromArray([
            'driver' => 'openai',
            'apiUrl' => 'https://api.openai.com/v1',
            'endpoint' => '/chat/completions',
            'model' => 'gpt-4.1-nano',
        ]));
    }

    public static function using(string $preset, ?string $basePath = null): self {
        return self::fromLLMConfig(LLMConfig::fromPreset($preset, $basePath));
    }

    public static function fromLLMConfig(LLMConfig $config): self {
        return new self(config: $config);
    }

    public static function fromArray(array $config): self {
        return self::fromLLMConfig(LLMConfig::fromArray($config));
    }

    // ACCESSORS /////////////////////////////////////////////////////////////

    #[\Override]
    public function resolveConfig(): LLMConfig {
        return $this->config;
    }

    #[\Override]
    public function explicitInferenceDriver(): ?CanProcessInferenceRequest {
        return $this->explicitDriver;
    }

    // MUTATORS //////////////////////////////////////////////////////////////

    public function with(
        ?LLMConfig $config = null,
        ?CanProcessInferenceRequest $explicitDriver = null,
    ): self {
        return new self(
            config: $config ?? $this->config,
            explicitDriver: $explicitDriver ?? $this->explicitDriver,
        );
    }

    #[\Override]
    public function withLLMConfig(LLMConfig $config): static {
        return $this->with(config: $config);
    }

    public function withConfigOverrides(array $overrides): self {
        return $this->with(config: $this->config->withOverrides($overrides));
    }

    public function withDriver(CanProcessInferenceRequest $driver): self {
        return $this->with(explicitDriver: $driver);
    }

    public function withModel(string $model): self {
        return $this->with(config: $this->config->withOverrides(['model' => $model]));
    }
}
