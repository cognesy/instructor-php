<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision;

use Cognesy\Config\EnvTemplate;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Contracts\CanProcessDecisionRequest;
use Cognesy\Polyglot\Decision\Contracts\CanResolveDecisionConfig;
use Cognesy\Polyglot\Decision\Contracts\HasExplicitDecisionDriver;
use Override;

final readonly class DecisionProvider implements CanResolveDecisionConfig, HasExplicitDecisionDriver
{
    private function __construct(
        private DecisionConfig $config,
        private ?CanProcessDecisionRequest $explicitDriver = null,
    ) {}

    public static function new(?DecisionConfig $config = null, ?EnvTemplate $template = null): self
    {
        return new self($config ?? DecisionConfig::fromDefaults($template));
    }

    public static function using(
        string $preset,
        ?string $basePath = null,
        ?EnvTemplate $template = null,
    ): self {
        return new self(DecisionConfig::fromPreset($preset, $basePath, $template));
    }

    public static function fromDecisionConfig(DecisionConfig $config): self
    {
        return new self($config);
    }

    public static function fromArray(array $config): self
    {
        return new self(DecisionConfig::fromArray($config));
    }

    #[Override]
    public function resolveConfig(): DecisionConfig
    {
        return $this->config;
    }

    #[Override]
    public function explicitDecisionDriver(): ?CanProcessDecisionRequest
    {
        return $this->explicitDriver;
    }

    public function withConfig(DecisionConfig $config): self
    {
        return new self($config, $this->explicitDriver);
    }

    public function withConfigOverrides(array $overrides): self
    {
        return $this->withConfig($this->config->withOverrides($overrides));
    }

    public function withDriver(CanProcessDecisionRequest $driver): self
    {
        return new self($this->config, $driver);
    }

    public function withModel(string $model): self
    {
        return $this->withConfigOverrides(['model' => $model]);
    }
}
