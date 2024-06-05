<?php

namespace Cognesy\Instructor\ApiClient\Enums;

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
use Exception;

enum ClientType : string
{
    case Anthropic = 'anthropic';
    case Anyscale = 'anyscale';
    case Azure = 'azure';
    case Cohere = 'cohere';
    case Fireworks = 'fireworks';
    case Groq = 'groq';
    case Mistral = 'mistral';
    case Ollama = 'ollama';
    case OpenAI = 'openai';
    case OpenRouter = 'openrouter';
    case Together = 'together';
    case OpenAICompatible = 'openai-compatible';

    public static function fromString(string $type) : self {
        return match($type) {
            'anthropic' => self::Anthropic,
            'anyscale' => self::Anyscale,
            'azure' => self::Azure,
            'cohere' => self::Cohere,
            'fireworks' => self::Fireworks,
            'groq' => self::Groq,
            'mistral' => self::Mistral,
            'ollama' => self::Ollama,
            'openai' => self::OpenAI,
            'openrouter' => self::OpenRouter,
            'together' => self::Together,
            default => throw new Exception("Unknown client type: $type"),
        };
    }

    public function toClientClass() : string {
        return match($this) {
            self::Anthropic => AnthropicClient::class,
            self::Anyscale => AnyscaleClient::class,
            self::Azure => AzureClient::class,
            self::Cohere => CohereClient::class,
            self::Fireworks => FireworksAIClient::class,
            self::Groq => GroqClient::class,
            self::Mistral => MistralClient::class,
            self::Ollama => OllamaClient::class,
            self::OpenAI => OpenAIClient::class,
            self::OpenRouter => OpenRouterClient::class,
            self::Together => TogetherAIClient::class,
        };
    }

    public static function fromClientClass(string $class) : self {
        return match($class) {
            AnthropicClient::class => self::Anthropic,
            AnyscaleClient::class => self::Anyscale,
            AzureClient::class => self::Azure,
            CohereClient::class => self::Cohere,
            FireworksAIClient::class => self::Fireworks,
            GroqClient::class => self::Groq,
            MistralClient::class => self::Mistral,
            OllamaClient::class => self::Ollama,
            OpenAIClient::class => self::OpenAI,
            OpenRouterClient::class => self::OpenRouter,
            TogetherAIClient::class => self::Together,
            default => throw new Exception("Unknown client class: $class"),
        };
    }
}
