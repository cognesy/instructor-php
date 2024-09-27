<?php
namespace Cognesy\Instructor\ApiClient\Enums;

enum ClientType : string
{
    use Traits\HandlesAccess;
    use Traits\HandlesCreation;
    use Traits\HandlesMapping;
    use Traits\HandlesResponse;
    use Traits\HandlesStreamData;

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
    case Unknown = 'unknown';
}
