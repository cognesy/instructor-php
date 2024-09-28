<?php
namespace Cognesy\Instructor\Extras\LLM\Enums;

enum LLMProviderType : string
{
    case Anthropic = 'anthropic';
    //case Anyscale = 'anyscale';
    case Azure = 'azure';
    case Cohere = 'cohere';
    case Fireworks = 'fireworks';
    case Gemini = 'gemini';
    case Groq = 'groq';
    case Jina = 'jina';
    case Mistral = 'mistral';
    case Ollama = 'ollama';
    case OpenAI = 'openai';
    case OpenRouter = 'openrouter';
    case Together = 'together';
    case OpenAICompatible = 'openai-compatible';
    case Other = 'other';
    case Unknown = 'unknown';

    public function is(LLMProviderType $clientType) : bool {
        return $this->value === $clientType->value;
    }
}
