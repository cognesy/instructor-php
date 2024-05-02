<?php

namespace Cognesy\Instructor\ApiClient\Enums;

enum ClientType : string
{
    case Anthropic = 'anthropic';
    case Anyscale = 'anyscale';
    case Azure = 'azure';
    case Fireworks = 'fireworks';
    case Groq = 'groq';
    case Mistral = 'mistral';
    case Ollama = 'ollama';
    case OpenAI = 'openai';
    case OpenRouter = 'openrouter';
    case Together = 'together';
}
