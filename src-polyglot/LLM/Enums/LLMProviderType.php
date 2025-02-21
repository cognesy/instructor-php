<?php
namespace Cognesy\Polyglot\LLM\Enums;

enum LLMProviderType : string
{
    case A21 = 'a21';
    case Anthropic = 'anthropic';
    //case Anyscale = 'anyscale';
    case Azure = 'azure';
    case Cerebras = 'cerebras';
    case CohereV1 = 'cohere1';
    case CohereV2 = 'cohere2';
    case DeepSeek = 'deepseek';
    case Fireworks = 'fireworks';
    case Gemini = 'gemini';
    case GeminiOAI = 'gemini-oai';
    case Groq = 'groq';
    case Jina = 'jina';
    case Minimaxi = 'minimaxi';
    case Mistral = 'mistral';
    case Moonshot = 'moonshot';
    case Ollama = 'ollama';
    case OpenAI = 'openai';
    case OpenRouter = 'openrouter';
    case Perplexity = 'perplexity';
    case SambaNova = 'sambanova';
    case Together = 'together';
    case XAi = 'xai';
    case OpenAICompatible = 'openai-compatible';
    case Other = 'other';
    case Unknown = 'unknown';

    public function is(LLMProviderType $clientType) : bool {
        return $this->value === $clientType->value;
    }
}
