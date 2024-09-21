<?php

namespace Cognesy\Instructor\Extras\Embeddings;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\Extras\Embeddings\Contracts\CanVectorize;
use Cognesy\Instructor\Extras\Embeddings\Drivers\AzureOpenAIDriver;
use Cognesy\Instructor\Extras\Embeddings\Drivers\CohereDriver;
use Cognesy\Instructor\Extras\Embeddings\Drivers\GeminiDriver;
use Cognesy\Instructor\Extras\Embeddings\Drivers\JinaDriver;
use Cognesy\Instructor\Extras\Embeddings\Drivers\OpenAIDriver;
use Cognesy\Instructor\Utils\Settings;
use GuzzleHttp\Client;
use InvalidArgumentException;

class Embeddings
{
    use Traits\HasFinders;

    protected Client $client;
    protected ClientType $clientType;

    protected EmbeddingsConfig $config;
    protected CanVectorize $driver;

    public function __construct() {
        $this->client = new Client();
        $this->config = EmbeddingsConfig::load(Settings::get('embed', "defaultConnection"));
        $this->driver = $this->getDriver($this->config->clientType);
    }

    public function withConnection(string $connection) : self {
        $this->config = EmbeddingsConfig::load($connection);
        $this->driver = $this->getDriver($this->config->clientType);
        return $this;
    }

    public function withModel(string $model) : self {
        $this->config->model = $model;
        return $this;
    }

    public function withVectorizer(CanVectorize $vectorizer) : self {
        $this->driver = $vectorizer;
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

    protected function getDriver(ClientType $clientType) : CanVectorize {
        return match ($clientType) {
            ClientType::Azure => new AzureOpenAIDriver($this->client, $this->config),
            ClientType::Cohere => new CohereDriver($this->client, $this->config),
            ClientType::Gemini => new GeminiDriver($this->client, $this->config),
            ClientType::Mistral => new OpenAIDriver($this->client, $this->config),
            ClientType::OpenAI => new OpenAIDriver($this->client, $this->config),
            ClientType::Ollama => new OpenAIDriver($this->client, $this->config),
            ClientType::Jina => new JinaDriver($this->client, $this->config),
            default => throw new InvalidArgumentException("Unknown client: {$this->client}"),
        };
    }
}
