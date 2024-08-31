<?php

namespace Cognesy\Instructor\ApiClient\Enums\Traits;

use Cognesy\Instructor\ApiClient\Contracts\CanCallLLM;
use Cognesy\Instructor\Clients\Anthropic\AnthropicApiRequest;
use Cognesy\Instructor\Clients\Anthropic\AnthropicClient;
//use Cognesy\Instructor\Clients\Anyscale\AnyscaleApiRequest;
//use Cognesy\Instructor\Clients\Anyscale\AnyscaleClient;
use Cognesy\Instructor\Clients\Azure\AzureApiRequest;
use Cognesy\Instructor\Clients\Azure\AzureClient;
use Cognesy\Instructor\Clients\Cohere\CohereApiRequest;
use Cognesy\Instructor\Clients\Cohere\CohereClient;
use Cognesy\Instructor\Clients\FireworksAI\FireworksAIApiRequest;
use Cognesy\Instructor\Clients\FireworksAI\FireworksAIClient;
use Cognesy\Instructor\Clients\Gemini\GeminiApiRequest;
use Cognesy\Instructor\Clients\Gemini\GeminiClient;
use Cognesy\Instructor\Clients\Groq\GroqApiRequest;
use Cognesy\Instructor\Clients\Groq\GroqClient;
use Cognesy\Instructor\Clients\Mistral\MistralApiRequest;
use Cognesy\Instructor\Clients\Mistral\MistralClient;
use Cognesy\Instructor\Clients\Ollama\OllamaApiRequest;
use Cognesy\Instructor\Clients\Ollama\OllamaClient;
use Cognesy\Instructor\Clients\OpenAI\OpenAIApiRequest;
use Cognesy\Instructor\Clients\OpenAI\OpenAIClient;
use Cognesy\Instructor\Clients\OpenRouter\OpenRouterApiRequest;
use Cognesy\Instructor\Clients\OpenRouter\OpenRouterClient;
use Cognesy\Instructor\Clients\TogetherAI\TogetherAIClient;
use Cognesy\Instructor\Clients\TogetherAI\TogetherApiRequest;

trait HandlesCreation
{
    public static function fromString(string $type) : self {
        return match($type) {
            'anthropic' => self::Anthropic,
            //'anyscale' => self::Anyscale,
            'azure' => self::Azure,
            'cohere' => self::Cohere,
            'fireworks' => self::Fireworks,
            'gemini' => self::Gemini,
            'groq' => self::Groq,
            'mistral' => self::Mistral,
            'ollama' => self::Ollama,
            'openai' => self::OpenAI,
            'openrouter' => self::OpenRouter,
            'together' => self::Together,
            default => self::OpenAICompatible,
        };
    }

    public static function fromRequestClass(string|object $class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }
        return match($class) {
            AnthropicApiRequest::class => self::Anthropic,
            //AnyscaleApiRequest::class => self::Anyscale,
            AzureApiRequest::class => self::Azure,
            CohereApiRequest::class => self::Cohere,
            FireworksAIApiRequest::class => self::Fireworks,
            GeminiApiRequest::class => self::Gemini,
            GroqApiRequest::class => self::Groq,
            MistralApiRequest::class => self::Mistral,
            OllamaApiRequest::class => self::Ollama,
            OpenAIApiRequest::class => self::OpenAI,
            OpenRouterApiRequest::class => self::OpenRouter,
            TogetherApiRequest::class => self::Together,
            default => self::OpenAICompatible,
        };
    }

    public static function fromClientClass(string|object $class) : self {
        if (is_object($class)) {
            $class = get_class($class);
        }
        return match($class) {
            AnthropicClient::class => self::Anthropic,
            //AnyscaleClient::class => self::Anyscale,
            AzureClient::class => self::Azure,
            CohereClient::class => self::Cohere,
            FireworksAIClient::class => self::Fireworks,
            GeminiClient::class => self::Gemini,
            GroqClient::class => self::Groq,
            MistralClient::class => self::Mistral,
            OllamaClient::class => self::Ollama,
            OpenAIClient::class => self::OpenAI,
            OpenRouterClient::class => self::OpenRouter,
            TogetherAIClient::class => self::Together,
            default => self::OpenAICompatible,
        };
    }

    public static function fromClient(CanCallLLM $client) : self {
        return match(true) {
            $client instanceof AnthropicClient => self::Anthropic,
            //is AnyscaleClient => self::Anyscale,
            $client instanceof AzureClient => self::Azure,
            $client instanceof CohereClient => self::Cohere,
            $client instanceof FireworksAIClient => self::Fireworks,
            $client instanceof GeminiClient => self::Gemini,
            $client instanceof GroqClient => self::Groq,
            $client instanceof MistralClient => self::Mistral,
            $client instanceof OllamaClient => self::Ollama,
            $client instanceof OpenAIClient => self::OpenAI,
            $client instanceof OpenRouterClient => self::OpenRouter,
            $client instanceof TogetherAIClient => self::Together,
            default => self::OpenAICompatible,
        };
    }
}