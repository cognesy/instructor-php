<?php
namespace Cognesy\Instructor\Extras\LLM\Traits;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\Extras\LLM\Contracts\CanHandleInference;
use Cognesy\Instructor\Extras\LLM\Drivers\AnthropicDriver;
use Cognesy\Instructor\Extras\LLM\Drivers\AzureOpenAIDriver;
use Cognesy\Instructor\Extras\LLM\Drivers\CohereDriver;
use Cognesy\Instructor\Extras\LLM\Drivers\GeminiDriver;
use Cognesy\Instructor\Extras\LLM\Drivers\MistralDriver;
use Cognesy\Instructor\Extras\LLM\Drivers\OpenAICompatibleDriver;
use Cognesy\Instructor\Extras\LLM\Drivers\OpenAIDriver;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
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

    protected function makeClient() : Client {
        if (isset($this->client) && $this->config->debugEnabled()) {
            throw new InvalidArgumentException("Guzzle does not allow to inject debugging stack into existing client. Turn off debug or use default client.");
        }
        return match($this->config->debugEnabled()) {
            false => $this->client ?? new Client(),
            true => new Client(['handler' => $this->addDebugStack(HandlerStack::create())]),
        };
    }
}