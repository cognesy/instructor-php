<?php

namespace Cognesy\Polyglot\LLM\Drivers\Perplexity;

use Cognesy\Polyglot\LLM\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Polyglot\LLM\InferenceRequest;
use Cognesy\Utils\Messages\Messages;

class PerplexityBodyFormat extends OpenAICompatibleBodyFormat
{
    public function toRequestBody(InferenceRequest $request) : array {
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

    protected function toResponseFormat(InferenceRequest $request) : array {
        $mode = $this->toResponseFormatMode($request);
        switch ($mode) {
            case OutputMode::Text:
            case OutputMode::MdJson:
                $result = ['type' => 'text'];
                break;
            case OutputMode::Json:
            case OutputMode::JsonSchema:
                [$schema, $schemaName, $schemaStrict] = $this->toSchemaData($request);
                $result = [
                    'type' => 'json_schema',
                    'json_schema' => ['schema' => $schema],
                ];
                break;
            default:
                $result = [];
        }

        return $result ?? [];
    }
}

// PERPLEXITY CUSTOM OPTIONS
// - search_domain_filter
// - return_images
// - return_related_questions
// - search_recency_filter
