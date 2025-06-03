<?php

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Contracts\CanProvideEmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Utils\Deferred;
use Cognesy\Utils\Dsn\DSN;
use Cognesy\Utils\Events\Contracts\CanRegisterEventListeners;
use Cognesy\Utils\Events\EventHandlerFactory;
use Cognesy\Utils\Events\Traits\HandlesEventDispatching;
use Cognesy\Utils\Events\Traits\HandlesEventListening;
use Psr\EventDispatcher\EventDispatcherInterface;

class EmbeddingsProvider
{
    use HandlesEventDispatching;
    use HandlesEventListening;

    protected Deferred $driver;
    protected Deferred $config;
    protected Deferred $httpClient;

    protected CanProvideEmbeddingsConfig $configProvider;
    protected ?bool $debug = null;

    public function __construct(
        ?EventDispatcherInterface  $events = null,
        ?CanRegisterEventListeners $listener = null,
        ?CanProvideEmbeddingsConfig $configProvider = null,
    ) {
        $eventHandlerFactory = new EventHandlerFactory($events, $listener);
        $this->events = $eventHandlerFactory->dispatcher();
        $this->listener = $eventHandlerFactory->listener();
        $this->configProvider = $configProvider ?? new SettingsEmbeddingsConfigProvider();

        $this->httpClient = $this->deferHttpClientCreation();
        $this->driver = $this->deferDriverCreation();
        $this->config = $this->deferConfigCreation();
    }

    // PUBLIC ///////////////////////////////////////////////////

    public function using(string $preset) : self {
        return $this->withPreset($preset);
    }

    public function withPreset(string $preset) : self {
        $this->config = $this->deferConfigCreation(preset: $preset);
        return $this;
    }

    public function withDsn(string $dsn) : self {
        $this->config = $this->deferConfigCreation(dsn: $dsn);
    }

    public function withConfig(EmbeddingsConfig $config) : self {
        $this->config = $this->deferConfigCreation(config: $config);
        return $this;
    }

    public function withHttpClient(HttpClient $httpClient) : self {
        $this->httpClient = $this->deferHttpClientCreation(httpClient: $httpClient);
        return $this;
    }

    public function withDriver(CanHandleVectorization $driver) : self {
        $this->driver = $this->deferDriverCreation(driver: $driver);
        return $this;
    }

    public function withDebug(bool $debug = true) : self {
        $this->debug = $debug;
        return $this;
    }

    public function config() : EmbeddingsConfig {
        return $this->config->resolve();
    }

    public function driver(): CanHandleVectorization {
        return $this->driver->resolve();
    }

    public function httpClient(): HttpClient {
        return $this->httpClient->resolve();
    }

    // INTERNAL ///////////////////////////////////////////////////

    private function deferDriverCreation(
        ?CanHandleVectorization $driver = null,
    ): Deferred {
        return new Deferred(fn() => $driver ?? $this->makeDriver());
    }

    private function deferConfigCreation(
        ?string $dsn = null,
        ?string $preset = null,
        ?EmbeddingsConfig $config = null,
    ): Deferred {
        return new Deferred(fn() => $config ?? $this->makeConfig($dsn, $preset));
    }

    private function deferHttpClientCreation(
        ?HttpClient $httpClient = null,
    ): Deferred {
        return new Deferred(fn() => $httpClient ?? $this->makeHttpClient());
    }

    private function makeConfig(
        ?string $dsn = null,
        ?string $preset = null,
    ) : EmbeddingsConfig {
        $params = DSN::fromString($dsn ?? '');
        $preset = $preset ?? $params->param('preset') ?? '';
        $dsnConfig = $params->toArray() ?? [];
        return match(true) {
            empty($preset) => match(true) {
                empty($dsn) => $this->configProvider->getConfig(),
                default => $this->configProvider->getConfig($preset)->withOverrides($dsnConfig),
            },
            default => $this->configProvider->getConfig($preset),
        };
    }

    private function makeHttpClient() : HttpClient {
        $httpClient = (new HttpClient($this->events, $this->listener))
            ->withPreset($this->config()->httpClient);
        return match(true) {
            is_null($this->debug) => $httpClient,
            default => $httpClient->withDebug($this->debug),
        };
    }

    private function makeDriver() : CanHandleVectorization {
        return (new EmbeddingsDriverFactory($this->events))
            ->makeDriver($this->config(), $this->httpClient());
    }
}