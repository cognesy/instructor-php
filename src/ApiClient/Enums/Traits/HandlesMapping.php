<?php
namespace Cognesy\Instructor\ApiClient\Enums\Traits;

use Cognesy\Instructor\Clients\Anthropic\AnthropicClient;
//use Cognesy\Instructor\Clients\Anyscale\AnyscaleClient;
use Cognesy\Instructor\Clients\Azure\AzureClient;
use Cognesy\Instructor\Clients\Cohere\CohereClient;
use Cognesy\Instructor\Clients\FireworksAI\FireworksAIClient;
use Cognesy\Instructor\Clients\Gemini\GeminiClient;
use Cognesy\Instructor\Clients\Groq\GroqClient;
use Cognesy\Instructor\Clients\Mistral\MistralClient;
use Cognesy\Instructor\Clients\Ollama\OllamaClient;
use Cognesy\Instructor\Clients\OpenAI\OpenAIClient;
use Cognesy\Instructor\Clients\OpenRouter\OpenRouterClient;
use Cognesy\Instructor\Clients\TogetherAI\TogetherAIClient;

trait HandlesMapping
{
    public function toNativeMessage(array $message) : array {
        return match($this) {
            self::Anthropic => ['role' => $this->mapRole($message['role']), 'content' => $message['content']],
            //self::Anyscale => $message,
            self::Azure => $message,
            self::Cohere => ['role' => $this->mapRole($message['role']), 'message' => $message['content']],
            self::Fireworks => $message,
            self::Gemini => ['role' => $this->mapRole($message['role']), "parts" => [["text" => $message['content']]]],
            self::Groq => $message,
            self::Mistral => $message,
            self::Ollama => $message,
            self::OpenAI => $message,
            self::OpenRouter => $message,
            self::Together => $message,
            self::OpenAICompatible => $message,
        };
    }

    public function toNativeMessages(string|array $messages) : array {
        if (is_string($messages)) {
            $messages = [['role' => 'user', 'content' => $messages]];
        }
        $transformed = [];
        foreach ($messages as $message) {
            $transformed[] = $this->toNativeMessage($message);
        }
        return $transformed;
    }

    public function toClientClass() : string {
        return match($this) {
            self::Anthropic => AnthropicClient::class,
            //self::Anyscale => AnyscaleClient::class,
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
            default => OpenAIClient::class,
        };
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////

    private function mapRole(string $role) : string {
        $map = $this->getRoleMap();
        return $map[$role] ?? $role;
    }

    private function getRoleMap() : array {
        return match($this) {
            self::Anthropic => ['user' => 'user', 'assistant' => 'assistant', 'system' => 'user', 'tool' => 'user'],
            //self::Anyscale => ['user' => 'user', 'assistant' => 'assistant', 'system' => 'system', 'tool' => 'tool'],
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
            self::OpenAICompatible => ['user' => 'user', 'assistant' => 'assistant', 'system' => 'system', 'tool' => 'tool'],
        };
    }
}