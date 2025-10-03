<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Perplexity;

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

class PerplexityBodyFormat extends OpenAICompatibleBodyFormat
{
    #[\Override]
    public function toRequestBody(InferenceRequest $request) : array {
        $request = $request->withCacheApplied();

        $options = array_merge($this->config->options, $request->options());

        $requestBody = array_merge(array_filter([
            'model' => $request->model() ?: $this->config->model,
            'max_tokens' => $this->config->maxTokens,
            'messages' => $this->messageFormat->map(Messages::fromArray($request->messages())->toMergedPerRole()->toArray()),
        ]), $options);

        // Perplexity does not support tools, so we unset them
        unset($requestBody['tools']);
        unset($requestBody['tool_choice']);

        $requestBody['response_format'] = $this->toResponseFormat($request);

        return array_filter($requestBody, fn($value) => $value !== null && $value !== [] && $value !== '');
    }

    // INTERNAL ///////////////////////////////////////////////

    #[\Override]
    protected function toResponseFormat(InferenceRequest $request) : array {
        $mode = $this->toResponseFormatMode($request);
        if ($mode === null) {
            return [];
        }

        // Perplexity API only supports: json_schema (with schema field only, no name/strict)
        // Both Json and JsonSchema modes use json_schema type
        $responseFormat = $request->responseFormat()
            ->withToJsonObjectHandler(fn() => [
                'type' => 'json_schema',
                'json_schema' => ['schema' => $this->removeDisallowedEntries($request->responseFormat()->schema())],
            ])
            ->withToJsonSchemaHandler(fn() => [
                'type' => 'json_schema',
                'json_schema' => ['schema' => $this->removeDisallowedEntries($request->responseFormat()->schema())],
            ]);

        return $responseFormat->as($mode);
    }
}

// PERPLEXITY CUSTOM OPTIONS
// - search_domain_filter
// - return_images
// - return_related_questions
// - search_recency_filter
