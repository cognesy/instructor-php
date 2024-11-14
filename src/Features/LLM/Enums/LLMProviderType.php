<?php
namespace Cognesy\Instructor\Features\LLM\Enums;

enum LLMProviderType : string
{
    case Anthropic = 'anthropic';
    //case Anyscale = 'anyscale';
    case Azure = 'azure';
    case CohereV1 = 'cohere1';
    case CohereV2 = 'cohere2';
    case Fireworks = 'fireworks';
    case Gemini = 'gemini';
    case GeminiOAI = 'gemini-oai';
    case Grok = 'grok';
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
