<?php

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Contracts\CanVectorize;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Drivers\AzureOpenAIDriver;
use Cognesy\Polyglot\Embeddings\Drivers\CohereDriver;
use Cognesy\Polyglot\Embeddings\Drivers\GeminiDriver;
use Cognesy\Polyglot\Embeddings\Drivers\JinaDriver;
use Cognesy\Polyglot\Embeddings\Drivers\OpenAIDriver;
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

    protected static array $drivers = [];
    protected EventDispatcher $events;
    protected EmbeddingsConfig $config;
    protected CanHandleHttpRequest $httpClient;
    protected CanVectorize $driver;

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
        $this->driver = $driver ?? $this->getDriver($this->config, $this->httpClient);
    }

    // PUBLIC ///////////////////////////////////////////////////

    public static function registerDriver(string $name, string|callable $driver) {
        self::$drivers[$name] = match(true) {
            is_string($driver) => fn($config, $httpClient, $events) => new $driver($config, $httpClient, $events),
            is_callable($driver) => $driver,
        };
    }

    /**
     * Configures the Embeddings instance with the given connection name.
     * @param string $connection
     * @return $this
     */
    public function withConnection(string $connection) : self {
        $this->config = EmbeddingsConfig::load($connection);
        $this->driver = $this->getDriver($this->config, $this->httpClient);
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given configuration.
     * @param EmbeddingsConfig $config
     * @return $this
     */
    public function withConfig(EmbeddingsConfig $config) : self {
        $this->config = $config;
        $this->driver = $this->getDriver($this->config, $this->httpClient);
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
        $this->driver = $this->getDriver($this->config, $this->httpClient);
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

    // INTERNAL /////////////////////////////////////////////////

    /**
     * Returns the driver for the specified configuration.
     *
     * @param EmbeddingsConfig $config
     * @param \Cognesy\Http\Contracts\CanHandleHttpRequest $httpClient
     * @return CanVectorize
     */
    protected function getDriver(EmbeddingsConfig $config, CanHandleHttpRequest $httpClient) : CanVectorize {
        $type = $config->type ?? 'openai';
        $driver = self::$drivers[$type] ?? $this->getBundledDriver($type);
        if (!$driver) {
            throw new InvalidArgumentException("Unknown driver: {$type}");
        }
        return $driver($config, $httpClient, $this->events);
    }

    protected function getBundledDriver(string $type) : ?callable {
        return match ($type) {
            'azure' => fn($config, $httpClient, $events) => new AzureOpenAIDriver($config, $httpClient, $events),
            'cohere1' => fn($config, $httpClient, $events) => new CohereDriver($config, $httpClient, $events),
            'gemini' => fn($config, $httpClient, $events) => new GeminiDriver($config, $httpClient, $events),
            'mistral' => fn($config, $httpClient, $events) => new OpenAIDriver($config, $httpClient, $events),
            'openai' => fn($config, $httpClient, $events) => new OpenAIDriver($config, $httpClient, $events),
            'ollama' => fn($config, $httpClient, $events) => new OpenAIDriver($config, $httpClient, $events),
            'jina' => fn($config, $httpClient, $events) => new JinaDriver($config, $httpClient, $events),
            default => null,
        };
    }
}
