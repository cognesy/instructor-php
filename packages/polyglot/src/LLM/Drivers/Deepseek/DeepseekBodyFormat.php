<?php

namespace Cognesy\Polyglot\LLM\Drivers\Deepseek;

use Cognesy\Polyglot\LLM\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Polyglot\LLM\InferenceRequest;
use Cognesy\Utils\Messages\Messages;
use Cognesy\Utils\Str;

class DeepseekBodyFormat extends OpenAICompatibleBodyFormat
{
    public function toRequestBody(InferenceRequest $request) : array {
        $options = array_merge($this->config->options, $request->options());

        $model = $request->model() ?: $this->config->model;
        $messages = match($this->supportsAlternatingRoles($request)) {
            false => Messages::fromArray($request->messages())->toMergedPerRole()->toArray(),
            true => $request->messages(),
        };

        $requestBody = array_merge(array_filter([
            'model' => $model,
            'max_tokens' => $this->config->maxTokens,
            'messages' => $this->messageFormat->map($messages),
        ]), $options);

        if ($options['stream'] ?? false) {
            $requestBody['stream_options']['include_usage'] = true;
        }

        $requestBody['response_format'] = $this->toResponseFormat($request);
        $requestBody['tools'] = $this->toTools($request);
        $requestBody['tool_choice'] = $this->toToolChoice($request);

        return $this->filterEmptyValues($requestBody);
    }

    // INTERNAL ///////////////////////////////////////////////

    protected function toResponseFormat(InferenceRequest $request) : array {
        if (!$this->supportsStructuredOutput($request)) {
            return ['type' => 'text'];
        }

        $mode = $this->toResponseFormatMode($request);
        switch ($mode) {
            case OutputMode::Json:
            case OutputMode::JsonSchema:
                $result = ['type' => 'json_object'];
                break;
            case OutputMode::Text:
            case OutputMode::MdJson:
                $result = ['type' => 'text'];
                break;
            default:
                $result = [];
        }

        return $result;
    }

    protected function supportsToolSelection(InferenceRequest $request) : bool {
        return !Str::contains($request->model(), 'reasoner');
    }

    protected function supportsStructuredOutput(InferenceRequest $request) : bool {
        return !Str::contains($request->model(), 'reasoner');
    }

    protected function supportsAlternatingRoles(InferenceRequest $request) : bool {
        return !Str::contains($request->model(), 'reasoner');
    }
}
