<?php

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Polyglot\Embeddings\Contracts\CanVectorize;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Polyglot\Embeddings\Traits\HasFinders;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Settings;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Embeddings is a facade responsible for generating embeddings for provided input data
 */
class Embeddings
{
    use HasFinders;

    protected EventDispatcherInterface $events;
    protected EmbeddingsProviderFactory $embeddingsProviderFactory;

    protected EmbeddingsProvider $provider;
    protected EmbeddingsRequest $request;

    public function __construct(
        string                $preset = '',
        EmbeddingsProvider    $provider = null,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->embeddingsProviderFactory = new EmbeddingsProviderFactory($this->events);
        $this->provider = $provider ?? $this->embeddingsProviderFactory->fromPreset($preset ?: Settings::get('embeddings', "defaultPreset"));
        $this->request = new EmbeddingsRequest();
    }

    // PUBLIC ///////////////////////////////////////////////////

    public function withProvider(EmbeddingsProvider $provider) : self {
        $this->provider = $provider;
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given connection name.
     * @param string $preset
     * @return $this
     */
    public function using(string $preset) : self {
        $this->provider->using($preset);
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given configuration.
     * @param EmbeddingsConfig $config
     * @return $this
     */
    public function withConfig(EmbeddingsConfig $config) : self {
        $this->provider->withConfig($config);
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given model name.
     * @param string $model
     * @return $this
     */
    public function withModel(string $model) : self {
        $this->provider->withModel($model);
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given HTTP client.
     *
     * @param \Cognesy\Http\Contracts\CanHandleHttpRequest $httpClient
     * @return $this
     */
    public function withHttpClient(CanHandleHttpRequest $httpClient) : self {
        $this->provider->withHttpClient($httpClient);
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given driver.
     * @param CanVectorize $driver
     * @return $this
     */
    public function withDriver(CanVectorize $driver) : self {
        $this->provider->withDriver($driver);
        return $this;
    }

    public function withInput(string|array $input) : self {
        $this->request->withAnyInput($input);
        return $this;
    }

    /**
     * Sets provided input and options data.
     * @param string|array $input
     * @param array $options
     * @return self
     */
    public function with(
        string|array $input = [],
        array $options = []
    ) : static {
        $this->request->withInput($input ?: $this->request->inputs());
        $this->request->withOptions(array_merge($this->request->options(), $options));
        return $this;
    }

    /**
     * Generates embeddings for the provided input data.
     * @return EmbeddingsResponse
     */
    public function create() : EmbeddingsResponse {
        if (empty($this->request->inputs())) {
            throw new InvalidArgumentException("Input data is required");
        }

        if (count($this->request->inputs()) > $this->config()->maxInputs) {
            throw new InvalidArgumentException("Number of inputs exceeds the limit of {$this->config()->maxInputs}");
        }

        return $this->driver()->vectorize(
            $this->request->inputs(),
            $this->request->options(),
        );
    }

    public function get() : EmbeddingsResponse {
        return $this->create();
    }

    /**
     * Returns all embeddings for the provided input data.
     *
     * @return Vector[] Array of embedding vectors
     */
    public function all() : array {
        return $this->create()->all();
    }

    /**
     * Returns the first embedding for the provided input data.
     *
     * @return Vector The first embedding vector
     */
    public function first() : EmbeddingsResponse {
        return $this->create()->first();
    }

    /**
     * Enable or disable debugging for the current instance.
     *
     * @param bool $debug Whether to enable debug mode. Default is true.
     *
     * @return self
     */
    public function withDebug(bool $debug = true) : self {
        $this->provider->withDebug($debug);
        return $this;
    }

    /**
     * Returns the config object for the current instance.
     *
     * @return EmbeddingsConfig The config object for the current instance.
     */
    public function config() : EmbeddingsConfig {
        return $this->provider->config();
    }

    /**
     * Returns the driver object for the current instance.
     *
     * @return CanVectorize The driver object for the current instance.
     */
    public function driver() : CanVectorize {
        return $this->provider->driver();
    }
}
