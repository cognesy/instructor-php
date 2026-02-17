<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference;

use Cognesy\Config\ConfigPresets;
use Cognesy\Config\ConfigResolver;
use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Config\Dsn;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanAcceptLLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanResolveLLMConfig;
use Cognesy\Polyglot\Inference\Contracts\HasExplicitInferenceDriver;

final class LLMProvider implements CanResolveLLMConfig, HasExplicitInferenceDriver, CanAcceptLLMConfig
{
    private readonly CanProvideConfig $configProvider;
    private ConfigPresets $presets;

    private ?string $dsn;
    private ?string $llmPreset;
    private ?array $configOverrides;
    private ?LLMConfig $explicitConfig;
    private ?CanProcessInferenceRequest $explicitDriver;

    private function __construct(
        ?CanProvideConfig           $configProvider = null,
        ?ConfigPresets              $presets = null,
        ?string                     $dsn = null,
        ?string                     $preset = null,
        ?LLMConfig                  $explicitConfig = null,
        ?CanProcessInferenceRequest $explicitDriver = null,
        ?array                      $configOverrides = null,
    ) {
        $this->configProvider = $configProvider ?? ConfigResolver::using($configProvider);
        $this->presets = $presets ?? ConfigPresets::using($this->configProvider)->for(LLMConfig::group());
        $this->dsn = $dsn;
        $this->llmPreset = $preset;
        $this->explicitConfig = $explicitConfig;
        $this->explicitDriver = $explicitDriver;
        $this->configOverrides = $configOverrides;
    }

    // FACTORIES /////////////////////////////////////////////////////////////

    public static function using(string $preset): self {
        return self::new()->withLLMPreset($preset);
    }

    public static function dsn(string $dsn): self {
        return self::new()->withDsn($dsn);
    }

    public static function new(
        ?CanProvideConfig $configProvider = null,
    ): self {
        return new self(configProvider: $configProvider);
    }

    // ACCESSORS /////////////////////////////////////////////////////////////

    #[\Override]
    public function resolveConfig(): LLMConfig {
        return $this->buildConfig();
    }

    #[\Override]
    public function explicitInferenceDriver(): ?CanProcessInferenceRequest {
        return $this->explicitDriver;
    }

    // MUTATORS //////////////////////////////////////////////////////////////

    public function with(
        ?string                     $dsn = null,
        ?string                     $preset = null,
        ?LLMConfig                  $explicitConfig = null,
        ?CanProcessInferenceRequest $explicitDriver = null,
        ?array                      $configOverrides = null,
        ?ConfigPresets              $presets = null,
    ): self {
        return new self(
            configProvider: $this->configProvider,
            presets: $presets ?? $this->presets,
            dsn: $dsn ?? $this->dsn,
            preset: $preset ?? $this->llmPreset,
            explicitConfig: $explicitConfig ?? $this->explicitConfig,
            explicitDriver: $explicitDriver ?? $this->explicitDriver,
            configOverrides: $configOverrides ?? $this->configOverrides,
        );
    }

    public function withLLMPreset(string $preset): self {
        return $this->with(preset: $preset);
    }

    #[\Override]
    public function withLLMConfig(LLMConfig $config): static {
        return $this->with(explicitConfig: $config);
    }

    public function withConfigOverrides(array $overrides): self {
        return $this->with(configOverrides: array_merge($this->configOverrides ?? [], $overrides));
    }

    public function withConfigProvider(CanProvideConfig $configProvider): self {
        return $this->with(presets: $this->presets->withConfigProvider($configProvider));
    }

    public function withDsn(string $dsn): self {
        return $this->with(dsn: $dsn);
    }

    public function withDriver(CanProcessInferenceRequest $driver): self {
        return $this->with(explicitDriver: $driver);
    }

    public function withModel(string $model): self {
        if ($this->explicitConfig !== null) {
            return $this->with(explicitConfig: $this->explicitConfig->withOverrides(['model' => $model]));
        }
        return $this->with(configOverrides: array_merge($this->configOverrides ?? [], ['model' => $model]));
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    private function buildConfig(): LLMConfig {
        // If explicit config provided, use it (with any overrides applied)
        if ($this->explicitConfig !== null) {
            return $this->configOverrides !== null
                ? $this->explicitConfig->withOverrides($this->configOverrides)
                : $this->explicitConfig;
        }

        // Get DSN overrides if any
        $dsn = Dsn::fromString($this->dsn);

        // Determine effective preset
        $effectivePreset = $this->determinePreset($dsn);

        // Build config based on preset
        $config = LLMConfig::fromArray($this->presets->getOrDefault($effectivePreset));

        // Apply DSN overrides if present
        $dsnOverrides = $dsn->without('preset')->toArray();
        $withDsn = !empty($dsnOverrides)
            ? $config->withOverrides($dsnOverrides)
            : $config;

        // Apply any additional overrides if specified
        return $this->configOverrides !== null
            ? $withDsn->withOverrides($this->configOverrides)
            : $withDsn;
    }

    private function determinePreset(Dsn $dsn): ?string {
        $dsnPreset = $dsn->param('preset');
        return match (true) {
            $this->llmPreset !== null => $this->llmPreset,
            $this->dsn !== null && is_string($dsnPreset) => $dsnPreset,
            default => null,
        };
    }
}
