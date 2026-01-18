<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference;

use Cognesy\Config\ConfigPresets;
use Cognesy\Config\ConfigResolver;
use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Config\Dsn;
use Cognesy\Config\Events\ConfigResolutionFailed;
use Cognesy\Config\Events\ConfigResolved;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanHandleInference;
use Cognesy\Polyglot\Inference\Contracts\CanResolveLLMConfig;
use Cognesy\Polyglot\Inference\Contracts\HasExplicitInferenceDriver;
use Cognesy\Utils\Result\Result;
use Psr\EventDispatcher\EventDispatcherInterface;

final class LLMProvider implements CanResolveLLMConfig, HasExplicitInferenceDriver
{
    private readonly CanHandleEvents $events;
    private readonly CanProvideConfig $configProvider;

    private ConfigPresets $presets;

    // Configuration - mutable via with* methods
    private ?string $dsn;
    private ?string $llmPreset;
    private ?array $configOverrides = null;

    private ?LLMConfig $explicitConfig;
    private ?CanHandleInference $explicitDriver;

    // HTTP client is no longer owned here (moved to facades)

    private function __construct(
        null|CanHandleEvents|EventDispatcherInterface $events = null,
        ?CanProvideConfig         $configProvider = null,
        ?string                   $dsn = null,
        ?string                   $preset = null,
        ?LLMConfig                $explicitConfig = null,
        ?CanHandleInference       $explicitDriver = null,
    ) {
        $this->events = EventBusResolver::using($events);
        $this->configProvider = $configProvider ?? ConfigResolver::using($configProvider);
        $this->presets = ConfigPresets::using($this->configProvider)->for(LLMConfig::group());

        $this->dsn = $dsn;
        $this->llmPreset = $preset;
        $this->explicitConfig = $explicitConfig;
        $this->explicitDriver = $explicitDriver;
    }

    public static function using(string $preset): LLMProvider {
        return self::new()->withLLMPreset($preset);
    }

    public static function dsn(string $dsn): LLMProvider {
        return self::new()->withDsn($dsn);
    }

    public static function new(
        ?EventDispatcherInterface $events = null,
        ?CanProvideConfig         $configProvider = null,
    ): self {
        return new self($events, $configProvider);
    }

    /**
     * Resolves and returns the effective LLM configuration for this provider.
     */
    #[\Override]
    public function resolveConfig(): LLMConfig {
        return $this->buildConfig();
    }

    #[\Override]
    public function explicitInferenceDriver(): ?CanHandleInference {
        return $this->explicitDriver;
    }

    public function withLLMPreset(string $preset): self {
        $this->llmPreset = $preset;
        return $this;
    }

    public function withConfig(LLMConfig $config): self {
        $this->explicitConfig = $config;
        return $this;
    }

    public function withConfigOverrides(array $overrides): self {
        $this->configOverrides = $overrides;
        return $this;
    }

    public function withConfigProvider(CanProvideConfig $configProvider): self {
        $this->presets = $this->presets->withConfigProvider($configProvider);
        return $this;
    }

    public function withDsn(string $dsn): self {
        $this->dsn = $dsn;
        return $this;
    }

    public function withDriver(CanHandleInference $driver): self {
        $this->explicitDriver = $driver;
        return $this;
    }

    public function withModel(string $model) : static {
        if ($this->explicitConfig !== null) {
            $this->explicitConfig = $this->explicitConfig->withOverrides(['model' => $model]);
        } else {
            $this->configOverrides = array_merge($this->configOverrides ?? [], ['model' => $model]);
        }
        return $this;
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    /**
     * Build the LLM configuration
     */
    private function buildConfig(): LLMConfig {
        // If explicit config provided, use it
        if ($this->explicitConfig !== null) {
            $this->events->dispatch(new ConfigResolved([
                'group' => 'llm',
                'config' => $this->explicitConfig->toArray()
            ]));
            return $this->explicitConfig;
        }

        // Get DSN overrides if any
        $dsn = Dsn::fromString($this->dsn);

        // Determine effective preset
        $effectivePreset = $this->determinePreset($dsn);

        // Build config based on preset
        $result = Result::try(fn() => $this->presets->getOrDefault($effectivePreset));

        if ($result->isFailure()) {
            $this->events->dispatch(new ConfigResolutionFailed([
                'group' => 'llm',
                'effectivePreset' => $effectivePreset,
                'preset' => $this->llmPreset,
                'dsn' => $this->dsn,
                'error' => $result->exception()->getMessage(),
            ]));
            throw $result->exception();
        }

        $config = LLMConfig::fromArray($result->unwrap());

        // Apply DSN overrides if present
        $dsnOverrides = $dsn->without('preset')->toArray();
        $withDsn = !empty($dsnOverrides)
            ? $config->withOverrides($dsnOverrides)
            : $config;

        // Apply any additional overrides if specified
        $final = $this->configOverrides !== null
            ? $withDsn->withOverrides($this->configOverrides)
            : $withDsn;

        // Dispatch event
        $this->events->dispatch(new ConfigResolved([
            'group' => 'llm',
            'effectivePreset' => $effectivePreset,
            'preset' => $this->llmPreset,
            'dsn' => $this->dsn,
            'config' => $final->toArray(),
        ]));

        return $final;
    }

    // HTTP client building removed from provider.

    /**
     * Determine the effective preset from various sources
     */
    private function determinePreset(Dsn $dsn): ?string {
        return match (true) {
            $this->llmPreset !== null => $this->llmPreset,
            $this->dsn !== null => $dsn->param('preset'),
            default => null,
        };
    }
}
