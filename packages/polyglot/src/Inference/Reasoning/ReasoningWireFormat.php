<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Reasoning;

enum ReasoningWireFormat: string
{
    case NamedEffort = 'named_effort';
    case OpenResponses = 'open_responses';
    case Anthropic = 'anthropic';
    case Gemini = 'gemini';
    case BooleanThinking = 'boolean_thinking';
    case Cohere = 'cohere';
    case OpenRouter = 'openrouter';
    case Qwen = 'qwen';
}
