<?php

namespace Cognesy\Instructor\Data\Messages\Utils\Traits\MessageBuilder;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\Clients\Anthropic\AnthropicClient;
use Cognesy\Instructor\Clients\Anyscale\AnyscaleClient;
use Cognesy\Instructor\Clients\Azure\AzureClient;
use Cognesy\Instructor\Clients\Cohere\CohereClient;
use Cognesy\Instructor\Clients\FireworksAI\FireworksAIClient;
use Cognesy\Instructor\Clients\Groq\GroqClient;
use Cognesy\Instructor\Clients\Mistral\MistralClient;
use Cognesy\Instructor\Clients\Ollama\OllamaClient;
use Cognesy\Instructor\Clients\OpenAI\OpenAIClient;
use Cognesy\Instructor\Clients\OpenRouter\OpenRouterClient;
use Cognesy\Instructor\Clients\TogetherAI\TogetherAIClient;
use Cognesy\Instructor\Data\Messages\Script;

trait HandlesApiProviders
{
    private function getBuilder(string $clientClass) : callable {
        return match($clientClass) {
            AnthropicClient::class => fn($script) => $this->anthropic($script),
            CohereClient::class => fn($script) => $this->cohere($script),
            MistralClient::class => fn($script) => $this->mistral($script),
            OpenAIClient::class,
            AzureClient::class => fn($script) => $this->openAI($script),
            AnyscaleClient::class,
            FireworksAIClient::class,
            GroqClient::class,
            OllamaClient::class,
            OpenRouterClient::class,
            TogetherAIClient::class => fn($script) => $this->openAILike($script),
            default => fn() => [],
        };
    }

    private function anthropic(Script $script) : array {
        return [
            'system' => $script->select('system')->toString(),
            'messages' => $script
                ->select(['messages', 'data_ack', 'command', 'examples'])
                ->toNativeArray(ClientType::Anthropic),
        ];
    }

    private function cohere(Script $script) : array {
        return array_filter([
            'preamble' => $script->select('system')->toString(),
            'chat_history' => $script->select('messages')->toNativeArray(ClientType::Cohere),
            'message' => $script->select(['command', 'examples'])->toString(),
        ]);
    }

    private function mistral(Script $script) : array {
        return [
            'messages' => $script
                ->select(['system', 'command', 'data_ack', 'examples', 'messages'])
                ->toNativeArray(ClientType::Mistral),
        ];
    }

    private function openAI(Script $script) : array {
        return [
            'messages' => $script
                ->select(['system', 'messages', 'data_ack', 'command', 'examples'])
                ->toNativeArray(ClientType::OpenAI),
        ];
    }

    private function openAILike(Script $script) : array {
        return [
            'messages' => $script
                ->select(['system', 'messages', 'data_ack', 'command', 'examples'])
                ->toNativeArray(ClientType::OpenAICompatible),
        ];
    }
}
