<?php

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Polyglot\Embeddings\Contracts\CanVectorize;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Drivers\AzureOpenAIDriver;
use Cognesy\Polyglot\Embeddings\Drivers\CohereDriver;
use Cognesy\Polyglot\Embeddings\Drivers\GeminiDriver;
use Cognesy\Polyglot\Embeddings\Drivers\JinaDriver;
use Cognesy\Polyglot\Embeddings\Drivers\OpenAIDriver;
use Cognesy\Polyglot\Embeddings\Traits\HasFinders;
use Cognesy\Polyglot\Http\Contracts\CanHandleHttp;
use Cognesy\Polyglot\Http\HttpClient;
use Cognesy\Polyglot\LLM\Enums\LLMProviderType;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Settings;
use InvalidArgumentException;

class Embeddings
{
    use HasFinders;

    protected EventDispatcher $events;
    protected EmbeddingsConfig $config;
    protected CanHandleHttp $httpClient;
    protected CanVectorize $driver;

    public function __construct(
        string $connection = '',
        EmbeddingsConfig $config = null,
        CanHandleHttp $httpClient = null,
        CanVectorize $driver = null,
        EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->config = $config ?? EmbeddingsConfig::load($connection
            ?: Settings::get('embed', "defaultConnection")
        );
        $this->httpClient = $httpClient ?? HttpClient::make(client: $this->config->httpClient, events: $this->events);
        $this->driver = $driver ?? $this->getDriver($this->config, $this->httpClient);
    }

    // PUBLIC ///////////////////////////////////////////////////

    public function withConnection(string $connection) : self {
        $this->config = EmbeddingsConfig::load($connection);
        $this->driver = $this->getDriver($this->config, $this->httpClient);
        return $this;
    }

    public function withConfig(EmbeddingsConfig $config) : self {
        $this->config = $config;
        $this->driver = $this->getDriver($this->config, $this->httpClient);
        return $this;
    }

    public function withModel(string $model) : self {
        $this->config->model = $model;
        return $this;
    }

    public function withHttpClient(CanHandleHttp $httpClient) : self {
        $this->httpClient = $httpClient;
        $this->driver = $this->getDriver($this->config, $this->httpClient);
        return $this;
    }

    public function withDriver(CanVectorize $driver) : self {
        $this->driver = $driver;
        return $this;
    }

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

    protected function getDriver(EmbeddingsConfig $config, CanHandleHttp $httpClient) : CanVectorize {
        return match ($config->providerType) {
            LLMProviderType::Azure->value => new AzureOpenAIDriver($config, $httpClient, $this->events),
            LLMProviderType::CohereV1->value => new CohereDriver($config, $httpClient, $this->events),
            LLMProviderType::Gemini->value => new GeminiDriver($config, $httpClient, $this->events),
            LLMProviderType::Mistral->value => new OpenAIDriver($config, $httpClient, $this->events),
            LLMProviderType::OpenAI->value => new OpenAIDriver($config, $httpClient, $this->events),
            LLMProviderType::Ollama->value => new OpenAIDriver($config, $httpClient, $this->events),
            LLMProviderType::Jina->value => new JinaDriver($config, $httpClient, $this->events),
            default => throw new InvalidArgumentException("Unknown client: {$config->providerType}"),
        };
    }
}
