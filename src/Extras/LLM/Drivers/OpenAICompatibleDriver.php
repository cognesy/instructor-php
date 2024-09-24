<?php
namespace Cognesy\Instructor\Extras\LLM\Drivers;

use Cognesy\Instructor\Enums\Mode;

class OpenAICompatibleDriver extends OpenAIDriver
{
    // INTERNAL /////////////////////////////////////////////

    protected function getRequestBody(
        array $messages = [],
        string $model = '',
        array $tools = [],
        string|array $toolChoice = '',
        array $responseFormat = [],
        array $options = [],
        Mode $mode = Mode::Text,
    ) : array {
        $request = array_filter(array_merge([
            'model' => $model ?: $this->config->model,
            'max_tokens' => $this->config->maxTokens,
            'messages' => $messages,
        ], $options));

        $request = $this->applyMode($request, $mode, $tools, $toolChoice, $responseFormat);

        return $request;
    }

    protected function applyMode(
        array $request,
        Mode $mode,
        array $tools,
        string|array $toolChoice,
        array $responseFormat
    ) : array {
        switch($mode) {
            case Mode::Tools:
                $request['tools'] = $tools;
                $request['tool_choice'] = $toolChoice;
                break;
            case Mode::Json:
            case Mode::JsonSchema:
                $request['response_format'] = $responseFormat;
                break;
        }
        return $request;
    }
}
