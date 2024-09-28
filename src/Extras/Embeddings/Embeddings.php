<?php

namespace Cognesy\Instructor\Extras\Embeddings;

use Cognesy\Instructor\Extras\Embeddings\Contracts\CanVectorize;
use Cognesy\Instructor\Extras\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Instructor\Extras\Embeddings\Drivers\AzureOpenAIDriver;
use Cognesy\Instructor\Extras\Embeddings\Drivers\CohereDriver;
use Cognesy\Instructor\Extras\Embeddings\Drivers\GeminiDriver;
use Cognesy\Instructor\Extras\Embeddings\Drivers\JinaDriver;
use Cognesy\Instructor\Extras\Embeddings\Drivers\OpenAIDriver;
use Cognesy\Instructor\Extras\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Extras\Http\HttpClient;
use Cognesy\Instructor\Extras\LLM\Enums\LLMProviderType;
use Cognesy\Instructor\Utils\Settings;
use InvalidArgumentException;

class Embeddings
{
    use Traits\HasFinders;

    protected EmbeddingsConfig $config;
    protected CanHandleHttp $httpClient;
    protected CanVectorize $driver;

    public function __construct() {
        $this->httpClient = HttpClient::make();
        $this->config = EmbeddingsConfig::load(Settings::get('embed', "defaultConnection"));
        $this->driver = $this->getDriver($this->config, $this->httpClient);
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
            LLMProviderType::Azure => new AzureOpenAIDriver($config, $httpClient),
            LLMProviderType::Cohere => new CohereDriver($config, $httpClient),
            LLMProviderType::Gemini => new GeminiDriver($config, $httpClient),
            LLMProviderType::Mistral => new OpenAIDriver($config, $httpClient),
            LLMProviderType::OpenAI => new OpenAIDriver($config, $httpClient),
            LLMProviderType::Ollama => new OpenAIDriver($config, $httpClient),
            LLMProviderType::Jina => new JinaDriver($config, $httpClient),
            default => throw new InvalidArgumentException("Unknown client: {$config->providerType->value}"),
        };
    }
}
