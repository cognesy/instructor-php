<?php
namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Http\HttpClient;
use Cognesy\Http\HttpClientBuilder;
use Cognesy\Polyglot\Embeddings\ConfigProviders\EmbeddingsConfigSource;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Contracts\CanProvideEmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Utils\Dsn\DSN;
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
    private CanProvideEmbeddingsConfig $configProvider;

    private ?string $preset;
    private ?string $dsn;
    private ?bool $debug;
    private ?EmbeddingsConfig $explicitConfig;
    private ?HttpClient $explicitHttpClient;
    private ?CanHandleVectorization $explicitDriver;

    private function __construct(
        ?EventDispatcherInterface $events = null,
        ?CanRegisterEventListeners $listener = null,
        ?CanProvideEmbeddingsConfig $configProvider = null,
        ?string $preset = null,
        ?string $dsn = null,
        ?bool $debug = null,
        ?EmbeddingsConfig $explicitConfig = null,
        ?HttpClient $explicitHttpClient = null,
        ?CanHandleVectorization $explicitDriver = null,
    ) {
        $eventHandlerFactory = new EventHandlerFactory($events, $listener);
        $this->events = $eventHandlerFactory->dispatcher();
        $this->listener = $eventHandlerFactory->listener();
        $this->configProvider = EmbeddingsConfigSource::makeWith($configProvider);

        $this->preset = $preset;
        $this->dsn = $dsn;
        $this->debug = $debug;
        $this->explicitConfig = $explicitConfig;
        $this->explicitHttpClient = $explicitHttpClient;
        $this->explicitDriver = $explicitDriver;
    }

    /**
     * Create a new builder instance
     */
    public static function new(
        ?EventDispatcherInterface $events = null,
        ?CanRegisterEventListeners $listener = null,
        ?CanProvideEmbeddingsConfig $configProvider = null,
    ): self {
        return new self($events, $listener, $configProvider);
    }

    /**
     * Quick creation with preset
     */
    public static function using(string $preset): self {
        return self::new()->withPreset($preset);
    }

    /**
     * Quick creation with DSN
     */
    public static function dsn(string $dsn): self {
        return self::new()->withDsn($dsn);
    }

    /**
     * Configure with a preset name
     */
    public function withPreset(string $preset): self {
        $this->preset = $preset;
        return $this;
    }

    /**
     * Configure with DSN string
     */
    public function withDsn(string $dsn): self {
        $this->dsn = $dsn;
        return $this;
    }

    /**
     * Configure with explicit embeddings configuration
     */
    public function withConfig(EmbeddingsConfig $config): self {
        $this->explicitConfig = $config;
        return $this;
    }

    /**
     * Configure with a custom config provider
     */
    public function withConfigProvider(CanProvideEmbeddingsConfig $configProvider): self {
        $this->configProvider = $configProvider;
        return $this;
    }

    /**
     * Configure with explicit HTTP client
     */
    public function withHttpClient(HttpClient $httpClient): self {
        $this->explicitHttpClient = $httpClient;
        return $this;
    }

    /**
     * Configure with explicit vectorization driver
     */
    public function withDriver(CanHandleVectorization $driver): self {
        $this->explicitDriver = $driver;
        return $this;
    }

    /**
     * Configure debug mode
     */
    public function withDebug(bool $debug = true): self {
        $this->debug = $debug;
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

    /**
     * Build the embeddings configuration
     */
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
            ? $this->configProvider->getConfig()
            : $this->configProvider->getConfig($effectivePreset);

        // Apply DSN overrides if present
        return !empty($dsnOverrides) ? $config->withOverrides($dsnOverrides) : $config;
    }

    /**
     * Build the HTTP client
     */
    private function buildHttpClient(EmbeddingsConfig $config): HttpClient {
        // If explicit client provided, use it
        if ($this->explicitHttpClient !== null) {
            return $this->explicitHttpClient;
        }

        // Build new client
        $builder = (new HttpClientBuilder($this->events, $this->listener))
            ->withPreset($config->httpClientPreset);

        // Apply debug setting if specified
        if ($this->debug !== null) {
            $builder = $builder->withDebug($this->debug);
        }

        return $builder->create();
    }

    /**
     * Determine the effective preset from various sources
     */
    private function determinePreset(): ?string {
        return match (true) {
            $this->preset !== null => $this->preset,
            $this->dsn !== null => DSN::fromString($this->dsn)->param('preset'),
            default => null,
        };
    }

    /**
     * Get DSN parameter overrides
     */
    private function getDsnOverrides(): array {
        if ($this->dsn === null) {
            return [];
        }
        return DSN::fromString($this->dsn)->toArray() ?? [];
    }
}