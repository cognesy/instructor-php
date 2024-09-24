<?php
namespace Cognesy\Instructor\Extras\LLM\Drivers;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Utils\Arrays;

class MistralDriver extends OpenAIDriver
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

    protected function removeDisallowedEntries(array $jsonSchema) : array {
        return Arrays::removeRecursively($jsonSchema, [
            'title',
            'description',
            'x-php-class',
            'additionalProperties',
        ]);
    }
}