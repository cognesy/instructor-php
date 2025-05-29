<?php

namespace Cognesy\Polyglot\LLM;

use Cognesy\Http\HttpClientFactory;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Utils\Events\EventDispatcher;
use Psr\EventDispatcher\EventDispatcherInterface;

class LLMFactory
{
    protected HttpClientFactory $httpClientFactory;
    protected InferenceDriverFactory $driverFactory;
    protected EventDispatcherInterface $events;

    /**
     * Constructor for initializing dependencies and configurations.
     *
     * @param EventDispatcher|null $events Event dispatcher.
     *
     * @return void
     */
    public function __construct(
        ?EventDispatcherInterface $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->httpClientFactory = new HttpClientFactory($this->events);
        $this->driverFactory = new InferenceDriverFactory($this->events);
    }

    // STATIC //////////////////////////////////////////////////////////////////

    public static function registerDriver(string $name, string|callable $driver): void {
        InferenceDriverFactory::registerDriver($name, $driver);
    }

    // PUBLIC //////////////////////////////////////////////////////////////////

    public function default(): LLM {
        $config = LLMConfig::default();
        return $this->fromConfig($config);
    }

    public function fromPreset(string $preset = ''): LLM {
        if (empty($preset)) {
            throw new \InvalidArgumentException("Preset name cannot be empty. Please provide a valid preset name.");
        }
        $config = LLMConfig::load($preset);
        return $this->fromConfig($config);
    }

    public function fromDSN(string $dsn): LLM {
        if (empty($dsn)) {
            throw new \InvalidArgumentException("DSN cannot be empty. Please provide a valid DSN.");
        }
        $config = LLMConfig::fromDSN($dsn);
        return $this->fromConfig($config);
    }

    /**
     * Updates the configuration and re-initializes the driver.
     *
     * @param LLMConfig $config The configuration object to set.
     *
     * @return self
     */
    public function fromConfig(LLMConfig $config): LLM {
        return new LLM(
            driver: $this->driverFactory->makeDriver(
                config: $config,
                httpClient: $this->httpClientFactory->fromPreset($config->httpClient)
            ),
            events: $this->events,
        );
    }

    public function forDriver(CanHandleInference $driver) {
        $config = LLMConfig::default();
        return new LLM(
            config: $config,
            httpClient: $this->httpClientFactory->fromPreset($config->httpClient),
            driver: $driver,
            events: $this->events,
        );
    }
}