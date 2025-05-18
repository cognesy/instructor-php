<?php

namespace Cognesy\Polyglot\LLM\Drivers\Perplexity;

use Cognesy\Polyglot\LLM\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Messages\Messages;

class PerplexityBodyFormat extends OpenAICompatibleBodyFormat
{
    public function map(
        array        $messages = [],
        string       $model = '',
        array        $tools = [],
        string|array $toolChoice = '',
        array        $responseFormat = [],
        array        $options = [],
        OutputMode   $mode = OutputMode::Unrestricted,
    ) : array {
        $options = array_merge($this->config->options, $options);

        $request = array_merge(array_filter([
            'model' => $model ?: $this->config->model,
            'max_tokens' => $this->config->maxTokens,
            'messages' => $this->messageFormat->map(Messages::fromArray($messages)->toMergedPerRole()->toArray()),
        ]), $options);

        unset($request['tools']);
        unset($request['tool_choice']);

        return $this->applyMode($request, $mode, $tools, $toolChoice, $responseFormat);
    }

    protected function applyMode(
        array        $request,
        OutputMode   $mode,
        array        $tools,
        string|array $toolChoice,
        array        $responseFormat
    ) : array {
        $request['response_format'] = $responseFormat ?: $request['response_format'] ?? [];

        switch($mode) {
            case OutputMode::Text:
            case OutputMode::MdJson:
                $request['response_format'] = ['type' => 'text'];
                break;
            case OutputMode::Json:
            case OutputMode::JsonSchema:
                $schema = $responseFormat['json_schema']['schema'] ?? $responseFormat['schema'] ?? [];
                $request['response_format'] = [
                    'type' => 'json_schema',
                    'json_schema' => ['schema' => $schema],
                ];
                break;
            case OutputMode::Unrestricted:
                $schema = $responseFormat['json_schema']['schema'] ?? $responseFormat['schema'] ?? [];
                $request['response_format'] = $request['response_format']
                    ? ['type' => 'json_schema', 'json_schema' => ['schema' => $schema]]
                    : ['type' => 'text'];
                break;
        }

        $request['tools'] = $tools ?? [];
        $request['tool_choice'] = $tools ? $this->toToolChoice($tools, $toolChoice) : [];

        $request['tools'] = $this->removeDisallowedEntries($request['tools']);
        $request['response_format'] = $this->removeDisallowedEntries($request['response_format']);

        return array_filter($request, fn($value) => $value !== null && $value !== [] && $value !== '');
    }
}

// PERPLEXITY CUSTOM OPTIONS
// - search_domain_filter
// - return_images
// - return_related_questions
// - search_recency_filter
