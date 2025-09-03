<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Config\ConfigPresets;
use Cognesy\Config\ConfigResolver;
use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Config\Dsn;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Drivers\EmbeddingsDriverFactory;
use Cognesy\Polyglot\Embeddings\Contracts\CanResolveEmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\HasExplicitEmbeddingsDriver;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Builder for creating fully configured embeddings vectorization drivers.
 * Once create() is called, returns a complete, ready-to-use instance.
 */
final class EmbeddingsProvider implements CanResolveEmbeddingsConfig, HasExplicitEmbeddingsDriver
{
    private readonly CanHandleEvents $events;
    private CanProvideConfig $configProvider;
    private ConfigPresets $presets;

    private ?string $preset;
    private ?string $dsn;
    private ?EmbeddingsConfig $explicitConfig;
    // HTTP client is no longer owned here (moved to facades)
    private ?CanHandleVectorization $explicitDriver;

    private function __construct(
        null|CanHandleEvents|EventDispatcherInterface $events = null,
        ?CanProvideConfig         $configProvider = null,
        ?string                   $preset = null,
        ?string                   $dsn = null,
        ?EmbeddingsConfig         $explicitConfig = null,
        ?CanHandleVectorization   $explicitDriver = null,
    ) {
        $this->events = EventBusResolver::using($events);
        $this->configProvider = ConfigResolver::using($configProvider);
        $this->presets = ConfigPresets::using($configProvider)->for(EmbeddingsConfig::group());

        $this->preset = $preset;
        $this->dsn = $dsn;
        $this->explicitConfig = $explicitConfig;
        $this->explicitDriver = $explicitDriver;
    }

    public static function new(
        ?CanHandleEvents          $events = null,
        ?CanProvideConfig         $configProvider = null,
    ): self {
        return new self($events, $configProvider);
    }

    public static function using(string $preset): self {
        return self::new()->withPreset($preset);
    }

    public static function dsn(string $dsn): self {
        return self::new()->withDsn($dsn);
    }

    public function withPreset(string $preset): self {
        $this->preset = $preset;
        return $this;
    }

    public function withDsn(string $dsn): self {
        $this->dsn = $dsn;
        return $this;
    }

    public function withConfig(EmbeddingsConfig $config): self {
        $this->explicitConfig = $config;
        return $this;
    }

    public function withConfigProvider(CanProvideConfig $configProvider): self {
        $this->presets = $this->presets->withConfigProvider($configProvider);
        $this->configProvider = ConfigResolver::using($configProvider);
        return $this;
    }

    // HTTP client configuration is owned by facades; related setters removed.

    public function withDriver(CanHandleVectorization $driver): self {
        $this->explicitDriver = $driver;
        return $this;
    }

    // Debug control moved to facades. No-op retained for BC for now.
    public function withDebugPreset(string $preset): self { return $this; }

    /**
     * Resolves and returns the effective embeddings configuration for this provider.
     */
    public function resolveConfig(): EmbeddingsConfig {
        return $this->buildConfig();
    }

    public function explicitEmbeddingsDriver(): ?CanHandleVectorization {
        return $this->explicitDriver;
    }

    // createDriver() removed — use resolveConfig() + EmbeddingsDriverFactory::makeDriver()

    // INTERNAL ////////////////////////////////////////////////////////////

    private function buildConfig(): EmbeddingsConfig {
        // If explicit config provided, use it
        if ($this->explicitConfig !== null) {
            return $this->explicitConfig;
        }

        // Determine effective preset
        $effectivePreset = $this->determinePreset();

        // Get DSN overrides if any
        $dsnOverrides = $this->getDsnOverrides();

        // Build config based on preset
        $config = $this->presets->getOrDefault($effectivePreset);

        // Apply DSN overrides if present
        $data = !empty($dsnOverrides) ? array_merge($config, $dsnOverrides) : $config;

        return EmbeddingsConfig::fromArray($data);
    }

    // HTTP client building removed from provider.

    private function determinePreset(): ?string {
        return match (true) {
            $this->preset !== null => $this->preset,
            $this->dsn !== null => Dsn::fromString($this->dsn)->param('preset'),
            default => null,
        };
    }

    private function getDsnOverrides(): array {
        if ($this->dsn === null) {
            return [];
        }
        return Dsn::fromString($this->dsn)->toArray() ?? [];
    }
}
