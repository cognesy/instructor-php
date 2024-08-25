<?php
namespace Cognesy\Instructor\ApiClient\Enums;

enum ClientType : string
{
    use Traits\HandlesCreation;
    use Traits\HandlesAccess;
    use Traits\HandlesMapping;

    case Anthropic = 'anthropic';
    //case Anyscale = 'anyscale';
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
}
