<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Mistral;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;

/**
 * Mistral request body: OpenAI-compatible with three provider deltas —
 * rejects parallel_tool_calls, still uses max_tokens (no
 * max_completion_tokens rename), and has no stream_options support.
 */
class MistralBodyFormat extends OpenAICompatibleBodyFormat
{
    #[\Override]
    protected function filterOptions(array $options): array
    {
        unset($options['parallel_tool_calls']);
        return $options;
    }

    #[\Override]
    protected function normalizeTokenLimits(array $requestBody): array
    {
        return $requestBody; // Mistral uses max_tokens as-is
    }

    #[\Override]
    protected function applyStreamOptions(array $requestBody, array $options): array
    {
        return $requestBody; // no stream_options support
    }

    #[\Override]
    protected function supportsNonTextResponseForTools(InferenceRequest $request): bool
    {
        return false;
    }

    // Mistral API supports json_object, json_schema and text -- exactly the base shapes, so
    // there is no response-format override here. There used to be one, carrying a comment
    // claiming Mistral filtered "through ResponseFormat's own filter hook rather than
    // filtering the rendered schema array (base behavior)". Those are the same operation:
    // schemaFilteredWith($f) is $f($this->schema()). The override reproduced the base payload
    // byte for byte, and the closure indirection is what made that hard to see.
}
