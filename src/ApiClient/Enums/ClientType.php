<?php

namespace Cognesy\Instructor\ApiClient\Enums;

use Cognesy\Instructor\Clients\Anthropic\AnthropicApiRequest;
use Cognesy\Instructor\Clients\Anthropic\AnthropicClient;
use Cognesy\Instructor\Clients\Anyscale\AnyscaleApiRequest;
use Cognesy\Instructor\Clients\Anyscale\AnyscaleClient;
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
use Exception;

enum ClientType : string
{
    case Anthropic = 'anthropic';
    case Anyscale = 'anyscale';
    case Azure = 'azure';
    case Cohere = 'cohere';
    case Fireworks = 'fireworks';
    case Gemini = 'gemini';
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
            'gemini' => self::Gemini,
            'groq' => self::Groq,
            'mistral' => self::Mistral,
            'ollama' => self::Ollama,
            'openai' => self::OpenAI,
            'openrouter' => self::OpenRouter,
            'together' => self::Together,
            default => throw new Exception("Unknown client type: $type"),
        };
    }

    public static function fromRequestClass(string $class)
    {
        return match($class) {
            AnthropicApiRequest::class => self::Anthropic,
            AnyscaleApiRequest::class => self::Anyscale,
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
            default => throw new Exception("Unknown client class: $class"),
        };
    }

    public function toClientClass() : string {
        return match($this) {
            self::Anthropic => AnthropicClient::class,
            self::Anyscale => AnyscaleClient::class,
            self::Azure => AzureClient::class,
            self::Cohere => CohereClient::class,
            self::Fireworks => FireworksAIClient::class,
            self::Gemini => GeminiClient::class,
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
            GeminiClient::class => self::Gemini,
            GroqClient::class => self::Groq,
            MistralClient::class => self::Mistral,
            OllamaClient::class => self::Ollama,
            OpenAIClient::class => self::OpenAI,
            OpenRouterClient::class => self::OpenRouter,
            TogetherAIClient::class => self::Together,
            default => throw new Exception("Unknown client class: $class"),
        };
    }

    public function getRoleMap() : array {
        return match($this) {
            self::Anthropic => ['user' => 'user', 'assistant' => 'assistant', 'system' => 'user', 'tool' => 'user'],
            self::Anyscale => ['user' => 'user', 'assistant' => 'assistant', 'system' => 'system', 'tool' => 'tool'],
            self::Azure => ['user' => 'user', 'assistant' => 'assistant', 'system' => 'system', 'tool' => 'tool'],
            self::Cohere => ['user' => 'USER', 'assistant' => 'CHATBOT', 'system' => 'USER', 'tool' => 'USER'],
            self::Fireworks => ['user' => 'user', 'assistant' => 'assistant', 'system' => 'system', 'tool' => 'tool'],
            self::Gemini => ['user' => 'user', 'assistant' => 'model', 'system' => 'user', 'tool' => 'tool'],
            self::Groq => ['user' => 'user', 'assistant' => 'assistant', 'system' => 'system', 'tool' => 'tool'],
            self::Mistral => ['user' => 'user', 'assistant' => 'assistant', 'system' => 'system', 'tool' => 'tool'],
            self::Ollama => ['user' => 'user', 'assistant' => 'assistant', 'system' => 'system', 'tool' => 'tool'],
            self::OpenAI => ['user' => 'user', 'assistant' => 'assistant', 'system' => 'system', 'tool' => 'tool'],
            self::OpenRouter => ['user' => 'user', 'assistant' => 'assistant', 'system' => 'system', 'tool' => 'tool'],
            self::Together => ['user' => 'user', 'assistant' => 'assistant', 'system' => 'system', 'tool' => 'tool'],
        };
    }

    public function mapRole(string $role) : string {
        $map = $this->getRoleMap();
        return $map[$role] ?? $role;
    }

    public function contentKey() : string {
        return match($this) {
            self::Anthropic => 'content',
            self::Anyscale => 'content',
            self::Azure => 'content',
            self::Cohere => 'message',
            self::Fireworks => 'content',
            self::Gemini => 'content',
            self::Groq => 'content',
            self::Mistral => 'content',
            self::Ollama => 'content',
            self::OpenAI => 'content',
            self::OpenRouter => 'content',
            self::Together => 'content',
        };
    }
}

//$roleMap = [
//    ClientType::Anthropic->value => ['user' => 'user', 'assistant' => 'assistant', 'system' => 'assistant', 'tool' => 'user'],
//    ClientType::Cohere->value => ['user' => 'USER', 'assistant' => 'CHATBOT', 'system' => 'CHATBOT', 'tool' => 'USER'],
//    ClientType::Mistral->value => ['user' => 'user', 'assistant' => 'assistant', 'system' => 'system', 'tool' => 'tool'],
//    ClientType::OpenAI->value => ['user' => 'user', 'assistant' => 'assistant', 'system' => 'system', 'tool' => 'tool'],
//    ClientType::OpenAICompatible->value => ['user' => 'user', 'assistant' => 'assistant', 'system' => 'system', 'tool' => 'tool'],
//];
//
//$keyMap = [
//    ClientType::Anthropic->value => 'content',
//    ClientType::Cohere->value => 'message',
//    ClientType::Mistral->value => 'content',
//    ClientType::OpenAICompatible->value => 'content',
//    ClientType::OpenAI->value => 'content',
//];
