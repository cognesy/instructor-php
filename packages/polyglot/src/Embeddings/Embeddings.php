<?php

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Contracts\CanVectorize;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Traits\HasFinders;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Settings;
use InvalidArgumentException;

/**
 * Embeddings is a facade responsible for generating embeddings for provided input data
 */
class Embeddings
{
    use HasFinders;

    protected EventDispatcher $events;
    protected EmbeddingsConfig $config;
    protected CanHandleHttpRequest $httpClient;
    protected CanVectorize $driver;
    protected EmbeddingsDriverFactory $driverFactory;

    public function __construct(
        string               $connection = '',
        ?EmbeddingsConfig     $config = null,
        ?CanHandleHttpRequest $httpClient = null,
        ?CanVectorize         $driver = null,
        ?EventDispatcher      $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->config = $config ?? EmbeddingsConfig::load($connection
            ?: Settings::get('embed', "defaultConnection")
        );
        $this->httpClient = $httpClient ?? HttpClient::make(client: $this->config->httpClient, events: $this->events);
        $this->driverFactory = new EmbeddingsDriverFactory($this->events);
        $this->driver = $driver ?? $this->driverFactory->makeDriver($this->config, $this->httpClient);
    }

    // PUBLIC ///////////////////////////////////////////////////

    public static function registerDriver(string $name, string|callable $driver) {
        EmbeddingsDriverFactory::registerDriver($name, $driver);
    }

    /**
     * Configures the Embeddings instance with the given connection name.
     * @param string $connection
     * @return $this
     */
    public function withConnection(string $connection) : self {
        $this->config = EmbeddingsConfig::load($connection);
        $this->driver = $this->driverFactory->makeDriver($this->config, $this->httpClient);
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given configuration.
     * @param EmbeddingsConfig $config
     * @return $this
     */
    public function withConfig(EmbeddingsConfig $config) : self {
        $this->config = $config;
        $this->driver = $this->driverFactory->makeDriver($this->config, $this->httpClient);
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given model name.
     * @param string $model
     * @return $this
     */
    public function withModel(string $model) : self {
        $this->config->model = $model;
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given HTTP client.
     *
     * @param \Cognesy\Http\Contracts\CanHandleHttpRequest $httpClient
     * @return $this
     */
    public function withHttpClient(CanHandleHttpRequest $httpClient) : self {
        $this->httpClient = $httpClient;
        $this->driver = $this->driverFactory->makeDriver($this->config, $this->httpClient);
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given driver.
     * @param CanVectorize $driver
     * @return $this
     */
    public function withDriver(CanVectorize $driver) : self {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Generates embeddings for the provided input data.
     * @param string|array $input
     * @param array $options
     * @return EmbeddingsResponse
     */
    public function create(string|array $input, array $options = []) : EmbeddingsResponse {
        if (is_string($input)) {
            $input = [$input];
        }
        if (count($input) > $this->config->maxInputs) {
            throw new InvalidArgumentException("Number of inputs exceeds the limit of {$this->config->maxInputs}");
        }
        return $this->driver->vectorize($input, $options);
    }
}
