<?php

namespace Cognesy\LLM\LLM\Drivers\Perplexity;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\LLM\LLM\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Utils\Messages\Messages;

class PerplexityBodyFormat extends OpenAICompatibleBodyFormat
{
    public function map(
        array $messages = [],
        string $model = '',
        array $tools = [],
        string|array $toolChoice = '',
        array $responseFormat = [],
        array $options = [],
        Mode $mode = Mode::Text,
    ) : array {
        $request = array_merge(array_filter([
            'model' => $model ?: $this->config->model,
            'max_tokens' => $this->config->maxTokens,
            'messages' => $this->messageFormat->map(Messages::fromArray($messages)->toMergedPerRole()->toArray()),
        ]), $options);

        if (!empty($tools)) {
            $request['tools'] = $this->removeDisallowedEntries($tools);
            $request['tool_choice'] = $this->toToolChoice($tools, $toolChoice);
        }

        return $this->applyMode($request, $mode, $tools, $toolChoice, $responseFormat);
    }
}

// PERPLEXITY CUSTOM OPTIONS
// - search_domain_filter
// - return_images
// - return_related_questions
// - search_recency_filter
