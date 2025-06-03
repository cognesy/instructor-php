<?php

namespace Cognesy\Polyglot\LLM;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Contracts\CanProvideLLMConfig;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Utils\Deferred;
use Cognesy\Utils\Dsn\DSN;
use Cognesy\Utils\Events\Contracts\CanRegisterEventListeners;
use Cognesy\Utils\Events\EventHandlerFactory;
use Psr\EventDispatcher\EventDispatcherInterface;

class LLMProvider
{
    protected EventDispatcherInterface $events;
    protected CanRegisterEventListeners $listener;
    protected CanProvideLLMConfig $configProvider;

    protected ?bool $debug = null;

    protected Deferred $httpClient;
    protected Deferred $config;
    protected Deferred $driver;

    public function __construct(
        ?EventDispatcherInterface $events = null,
        ?CanRegisterEventListeners $listener = null,
        ?CanProvideLLMConfig $configProvider = null,
    ) {
        $eventHandlerFactory = new EventHandlerFactory($events, $listener);
        $this->events = $eventHandlerFactory->dispatcher();
        $this->listener = $eventHandlerFactory->listener();
        $this->configProvider = $configProvider ?? new SettingsLLMConfigProvider();

        $this->config = $this->deferLLMConfigCreation();
        $this->httpClient = $this->deferHttpClientCreation();
        $this->driver = $this->deferInferenceDriverCreation();
    }

    // PUBLIC //////////////////////////////////////////////////////////////////

    public function using(string $preset): self {
        $this->config = $this->deferLLMConfigCreation(preset: $preset);
        return $this;
    }

    public function withConfig(LLMConfig $config): self {
        $this->config = $this->deferLLMConfigCreation(config: $config);
        return $this;
    }

    public function withConfigProvider(CanProvideLLMConfig $configProvider) : self {
        $this->configProvider = $configProvider;
        $this->config = $this->deferLLMConfigCreation();
        return $this;
    }

    public function withDSN(string $dsn): self {
        $this->config = $this->deferLLMConfigCreation(dsn: $dsn);
        return $this;
    }

    public function withHttpClient(?HttpClient $httpClient): self {
        $this->httpClient = $this->deferHttpClientCreation($httpClient);
        return $this;
    }

    public function withDriver(CanHandleInference $driver): self {
        $this->driver = $this->deferInferenceDriverCreation($driver);
        return $this;
    }

    public function withDebug(bool $debug = true) : self {
        $this->debug = $debug;
        return $this;
    }

    public function config() : LLMConfig {
        return $this->config->resolve();
    }

    public function driver() : CanHandleInference {
        return $this->driver->resolve();
    }

    public function httpClient(): HttpClient {
        return $this->httpClient->resolve();
    }

    // INTERNAL /////////////////////////////////////////////////////////////////

    private function deferInferenceDriverCreation(
        ?CanHandleInference $driver = null,
    ): Deferred {
        return new Deferred(fn() => $driver ?? $this->makeInferenceDriver());
    }

    private function deferHttpClientCreation(
        ?HttpClient $httpClient = null,
    ): Deferred {
        return new Deferred(fn() => $httpClient ?? $this->makeHttpClient());
    }

    private function deferLLMConfigCreation(
        ?string $dsn = null,
        ?string $preset = null,
        ?LLMConfig $config = null,
    ): Deferred {
        return new Deferred(fn() => $config ?? $this->makeLLMConfig($dsn, $preset));
    }

    private function makeLLMConfig(
        ?string $dsn = null,
        ?string $preset = null,
    ) : LLMConfig {
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

    private function makeInferenceDriver() : CanHandleInference {
        return (new InferenceDriverFactory(
            events: $this->events,
            listener: $this->listener
        ))->makeDriver(
            $this->config(),
            $this->httpClient(),
        );
    }

    private function makeHttpClient() : HttpClient {
        $httpClient = (new HttpClient($this->events, $this->listener))
            ->withPreset($this->config()->httpClientPreset);
        return match(true) {
            is_null($this->debug) => $httpClient,
            default => $httpClient->withDebug($this->debug),
        };
    }
}