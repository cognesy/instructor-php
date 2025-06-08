<?php
namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Http\HttpClient;
use Cognesy\Http\HttpClientBuilder;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Utils\Config\Contracts\CanProvideConfig;
use Cognesy\Utils\Config\Dsn\DSN;
use Cognesy\Utils\Config\Providers\ConfigResolver;
use Cognesy\Utils\Events\Contracts\CanRegisterEventListeners;
use Cognesy\Utils\Events\EventHandlerFactory;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Builder for creating fully configured embeddings vectorization drivers.
 * Once create() is called, returns a complete, ready-to-use instance.
 */
final class EmbeddingsProvider
{
    private readonly EventDispatcherInterface $events;
    private readonly CanRegisterEventListeners $listener;
    private CanProvideConfig $configProvider;

    private ?string $preset;
    private ?string $dsn;
    private ?string $debugPreset;
    private ?EmbeddingsConfig $explicitConfig;
    private ?HttpClient $explicitHttpClient;
    private ?CanHandleVectorization $explicitDriver;

    private function __construct(
        ?EventDispatcherInterface  $events = null,
        ?CanRegisterEventListeners $listener = null,
        ?CanProvideConfig          $configProvider = null,
        ?string                    $preset = null,
        ?string                    $dsn = null,
        ?string                    $debugPreset = null,
        ?EmbeddingsConfig          $explicitConfig = null,
        ?HttpClient                $explicitHttpClient = null,
        ?CanHandleVectorization    $explicitDriver = null,
    ) {
        $eventHandlerFactory = new EventHandlerFactory($events, $listener);
        $this->events = $eventHandlerFactory->dispatcher();
        $this->listener = $eventHandlerFactory->listener();
        $this->configProvider = ConfigResolver::makeWith($configProvider);

        $this->preset = $preset;
        $this->dsn = $dsn;
        $this->debugPreset = $debugPreset;
        $this->explicitConfig = $explicitConfig;
        $this->explicitHttpClient = $explicitHttpClient;
        $this->explicitDriver = $explicitDriver;
    }

    public static function new(
        ?EventDispatcherInterface  $events = null,
        ?CanRegisterEventListeners $listener = null,
        ?CanProvideConfig          $configProvider = null,
    ): self {
        return new self($events, $listener, $configProvider);
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
        return $this;
    }

    public function withHttpClient(HttpClient $httpClient): self {
        $this->explicitHttpClient = $httpClient;
        return $this;
    }

    public function withDriver(CanHandleVectorization $driver): self {
        $this->explicitDriver = $driver;
        return $this;
    }

    public function withDebugPreset(string $preset): self {
        $this->debugPreset = $preset;
        return $this;
    }

    /**
     * Create the fully configured vectorization driver
     * This is the terminal operation that builds and returns the final instance
     */
    public function createDriver(): CanHandleVectorization {
        // If explicit driver provided, return it directly
        if ($this->explicitDriver !== null) {
            return $this->explicitDriver;
        }

        // Build all required components
        $config = $this->buildConfig();
        $httpClient = $this->buildHttpClient($config);

        // Create and return the vectorization driver
        return (new EmbeddingsDriverFactory($this->events))
            ->makeDriver($config, $httpClient);
    }

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
        $config = empty($effectivePreset)
            ? $this->configProvider->getConfig('embed')
            : $this->configProvider->getConfig('embed', $effectivePreset);

        // Apply DSN overrides if present
        $data = !empty($dsnOverrides) ? array_merge($config, $dsnOverrides) : $config;

        return EmbeddingsConfig::fromArray($data);
    }

    private function buildHttpClient(EmbeddingsConfig $config): HttpClient {
        // If explicit client provided, use it
        if ($this->explicitHttpClient !== null) {
            return $this->explicitHttpClient;
        }

        // Build new client
        $builder = (new HttpClientBuilder($this->events, $this->listener))
            ->withPreset($config->httpClientPreset);

        // Apply debug setting if specified
        if ($this->debugPreset !== null) {
            $builder = $builder->withDebugPreset($this->debugPreset);
        }

        return $builder->create();
    }

    private function determinePreset(): ?string {
        return match (true) {
            $this->preset !== null => $this->preset,
            $this->dsn !== null => DSN::fromString($this->dsn)->param('preset'),
            default => null,
        };
    }

    private function getDsnOverrides(): array {
        if ($this->dsn === null) {
            return [];
        }
        return DSN::fromString($this->dsn)->toArray() ?? [];
    }
}