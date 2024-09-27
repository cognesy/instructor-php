<?php
namespace Cognesy\Instructor\Extras\LLM\Traits;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\Extras\Enums\HttpClientType;
use Cognesy\Instructor\Extras\LLM\Contracts\CanHandleHttp;
use Cognesy\Instructor\Extras\LLM\Contracts\CanHandleInference;
use Cognesy\Instructor\Extras\LLM\Drivers\AnthropicDriver;
use Cognesy\Instructor\Extras\LLM\Drivers\AzureOpenAIDriver;
use Cognesy\Instructor\Extras\LLM\Drivers\CohereDriver;
use Cognesy\Instructor\Extras\LLM\Drivers\GeminiDriver;
use Cognesy\Instructor\Extras\LLM\Drivers\MistralDriver;
use Cognesy\Instructor\Extras\LLM\Drivers\OpenAICompatibleDriver;
use Cognesy\Instructor\Extras\LLM\Drivers\OpenAIDriver;
use Cognesy\Instructor\Extras\LLM\Http\GuzzleHttpClient;
use InvalidArgumentException;

trait HandlesDrivers
{
    protected function getDriver(ClientType $clientType): CanHandleInference {
        return match ($clientType) {
            ClientType::Anthropic => new AnthropicDriver($this->makeClient(), $this->config),
            ClientType::Azure => new AzureOpenAIDriver($this->makeClient(), $this->config),
            ClientType::Cohere => new CohereDriver($this->makeClient(), $this->config),
            ClientType::Fireworks => new OpenAICompatibleDriver($this->makeClient(), $this->config),
            ClientType::Gemini => new GeminiDriver($this->makeClient(), $this->config),
            ClientType::Groq => new OpenAICompatibleDriver($this->makeClient(), $this->config),
            ClientType::Mistral => new MistralDriver($this->makeClient(), $this->config),
            ClientType::Ollama => new OpenAICompatibleDriver($this->makeClient(), $this->config),
            ClientType::OpenAI => new OpenAIDriver($this->makeClient(), $this->config),
            ClientType::OpenAICompatible => new OpenAICompatibleDriver($this->makeClient(), $this->config),
            ClientType::OpenRouter => new OpenAICompatibleDriver($this->makeClient(), $this->config),
            ClientType::Together => new OpenAICompatibleDriver($this->makeClient(), $this->config),
            default => throw new InvalidArgumentException("Client not supported: {$clientType->value}"),
        };
    }

    protected function makeClient() : CanHandleHttp {
        return match ($this->config->httpClient) {
            HttpClientType::Guzzle => new GuzzleHttpClient($this->config),
            default => throw new InvalidArgumentException("Client not supported: {$this->config->httpClient}"),
        };
    }
}