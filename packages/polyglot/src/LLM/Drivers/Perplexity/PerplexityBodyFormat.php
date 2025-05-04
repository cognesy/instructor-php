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
        OutputMode   $mode = OutputMode::Text,
    ) : array {
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

        $request['tools'] = $tools ? $this->removeDisallowedEntries($tools) : [];
        $request['tool_choice'] = $tools ? $this->toToolChoice($tools, $toolChoice) : [];

        return array_filter($request);
    }
}

// PERPLEXITY CUSTOM OPTIONS
// - search_domain_filter
// - return_images
// - return_related_questions
// - search_recency_filter
