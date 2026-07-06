<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Mistral;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Support\RequestPayload;

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

    // Mistral filters the schema through ResponseFormat's own filter hook
    // rather than filtering the rendered schema array (base behavior).
    #[\Override]
    protected function toResponseFormat(InferenceRequest $request): array
    {
        $type = $this->toResponseFormatType($request);
        if ($type === null) {
            return [];
        }

        // Mistral API supports: json_object, json_schema, text
        $responseFormat = $request->responseFormat()
            ->withToJsonObjectHandler(fn () => ['type' => 'json_object'])
            ->withToJsonSchemaHandler(fn () => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $request->responseFormat()->schemaName(),
                    'schema' => $request->responseFormat()->schemaFilteredWith($this->removeDisallowedEntries(...)),
                    'strict' => $request->responseFormat()->strict(),
                ],
            ]);

        $result = $this->renderResponseFormatForType($responseFormat, $type);

        return RequestPayload::filterEmptyValues($result);
    }
}
