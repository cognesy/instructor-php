<?php
namespace Cognesy\Instructor\Features\LLM\Drivers;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Utils\Arrays;

class MistralDriver extends OpenAIDriver
{
    // REQUEST //////////////////////////////////////////////

    public function getRequestBody(
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

        return $this->applyMode($request, $mode, $tools, $toolChoice, $responseFormat);
    }

    // PRIVATE //////////////////////////////////////////////

    private function applyMode(
        array $request,
        Mode $mode,
        array $tools,
        string|array $toolChoice,
        array $responseFormat
    ) : array {
        switch($mode) {
            case Mode::Tools:
                $request['tools'] = $this->removeDisallowedEntries($tools);
                $request['tool_choice'] = 'any';
                break;
            case Mode::Json:
            case Mode::JsonSchema:
                $request['response_format'] = ['type' => 'json_object'];
                break;
        }
        return $request;
    }

    private function removeDisallowedEntries(array $jsonSchema) : array {
        return Arrays::removeRecursively($jsonSchema, [
            'title',
            //'description',
            'x-php-class',
            'additionalProperties',
        ]);
    }
}