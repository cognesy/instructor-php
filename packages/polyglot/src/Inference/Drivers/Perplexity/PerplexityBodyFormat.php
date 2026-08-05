<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Perplexity;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;

class PerplexityBodyFormat extends OpenAICompatibleBodyFormat
{
    #[\Override]
    public function toRequestBody(InferenceRequest $request): array
    {
        $request = $request->withCacheApplied();

        $options = array_merge($this->config->options, $request->options());

        $requestBody = array_merge(array_filter([
            'model' => $request->model() ?: $this->config->model,
            'max_tokens' => $this->config->maxTokens,
            'messages' => $this->messageFormat->map($request->messages()->toMergedPerRole()),
        ]), $options);

        // Perplexity does not support tools, so we unset them
        unset($requestBody['tools']);
        unset($requestBody['tool_choice']);

        $requestBody['response_format'] = $this->toResponseFormat($request);

        return array_filter($requestBody, fn ($value) => $value !== null && $value !== [] && $value !== '');
    }

    // INTERNAL ///////////////////////////////////////////////

    // Perplexity only speaks json_schema, so plain JSON mode is sent as a schema payload too.
    #[\Override]
    protected function toJsonObjectResponseFormat(ResponseFormat $responseFormat): array
    {
        return $this->toJsonSchemaResponseFormat($responseFormat);
    }

    // The envelope carries the schema alone -- no name, no strict flag.
    #[\Override]
    protected function toJsonSchemaResponseFormat(ResponseFormat $responseFormat): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => ['schema' => $this->removeDisallowedEntries($responseFormat->schema())],
        ];
    }
}

// PERPLEXITY CUSTOM OPTIONS
// - search_domain_filter
// - return_images
// - return_related_questions
// - search_recency_filter
