<?php

namespace Cognesy\Instructor\Features\LLM\Drivers\OpenAI;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Features\LLM\Contracts\CanMapMessages;
use Cognesy\Instructor\Features\LLM\Contracts\CanMapRequestBody;
use Cognesy\Instructor\Features\LLM\Data\LLMConfig;

class OpenAIBodyFormat implements CanMapRequestBody
{
    public function __construct(
        protected LLMConfig $config,
        protected CanMapMessages $messageFormat,
    ) {}

    public function map(
        array $messages,
        string $model,
        array $tools,
        array|string $toolChoice,
        array $responseFormat,
        array $options,
        Mode $mode,
    ): array {
        $request = array_merge(array_filter([
            'model' => $model ?: $this->config->model,
            'max_tokens' => $this->config->maxTokens,
            'messages' => $this->messageFormat->map($messages),
        ]), $options);

        if ($options['stream'] ?? false) {
            $request['stream_options']['include_usage'] = true;
        }

        if (!empty($tools)) {
            $request['tools'] = $tools;
            $request['tool_choice'] = $toolChoice;
        }

        return $this->applyMode($request, $mode, $tools, $toolChoice, $responseFormat);
    }

    // INTERNAL ///////////////////////////////////////////////

    private function applyMode(
        array $request,
        Mode $mode,
        array $tools,
        string|array $toolChoice,
        array $responseFormat
    ) : array {
        switch($mode) {
            case Mode::Json:
                $request['response_format'] = ['type' => 'json_object'];
                break;
            case Mode::JsonSchema:
                $request['response_format'] = $responseFormat;
                break;
            case Mode::Text:
            case Mode::MdJson:
                $request['response_format'] = ['type' => 'text'];
                break;
        }
        return $request;
    }
}