<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Config\ConfigPresets;
use Cognesy\Config\ConfigResolver;
use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Config\Dsn;
use Cognesy\Config\Events\ConfigResolutionFailed;
use Cognesy\Config\Events\ConfigResolved;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Contracts\CanResolveEmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\HasExplicitEmbeddingsDriver;
use Cognesy\Utils\Result\Result;
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
        $this->configProvider = $configProvider ?? ConfigResolver::using($configProvider);
        $this->presets = ConfigPresets::using($this->configProvider)->for(EmbeddingsConfig::group());

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
        $this->configProvider = $configProvider;
        $this->presets = $this->presets->withConfigProvider($configProvider);
        return $this;
    }

    public function withDriver(CanHandleVectorization $driver): self {
        $this->explicitDriver = $driver;
        return $this;
    }

    /**
     * Resolves and returns the effective embeddings configuration for this provider.
     */
    #[\Override]
    public function resolveConfig(): EmbeddingsConfig {
        return $this->buildConfig();
    }

    #[\Override]
    public function explicitEmbeddingsDriver(): ?CanHandleVectorization {
        return $this->explicitDriver;
    }

    // INTERNAL ////////////////////////////////////////////////////////////

    private function buildConfig(): EmbeddingsConfig {
        if ($this->explicitConfig !== null) {
            $this->events->dispatch(new ConfigResolved([
                'group' => 'embeddings',
                'config' => $this->explicitConfig->toArray()
            ]));
            return $this->explicitConfig;
        }

        $effectivePreset = $this->determinePreset();
        $dsnOverrides = $this->getDsnOverrides();
        
        $result = Result::try(fn() => $this->presets->getOrDefault($effectivePreset));

        if ($result->isFailure()) {
            $this->events->dispatch(new ConfigResolutionFailed([
                'group' => 'embeddings',
                'effectivePreset' => $effectivePreset,
                'preset' => $this->preset,
                'dsn' => $this->dsn,
                'error' => $result->exception()->getMessage(),
            ]));
            throw $result->exception();
        }

        $config = $result->unwrap();
        $data = !empty($dsnOverrides) ? array_merge($config, $dsnOverrides) : $config;
        $final = EmbeddingsConfig::fromArray($data);

        $this->events->dispatch(new ConfigResolved([
            'group' => 'embeddings',
            'effectivePreset' => $effectivePreset,
            'preset' => $this->preset,
            'dsn' => $this->dsn,
            'config' => $final->toArray(),
        ]));

        return $final;
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
        return Dsn::fromString($this->dsn)->toArray();
    }
}
