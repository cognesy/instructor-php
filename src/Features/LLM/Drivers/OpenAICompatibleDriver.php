<?php
namespace Cognesy\Instructor\Features\LLM\Drivers;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Utils\Arrays;

class OpenAICompatibleDriver extends OpenAIDriver
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
        $request = array_merge(array_filter([
            'model' => $model ?: $this->config->model,
            'max_tokens' => $this->config->maxTokens,
            'messages' => $this->toNativeMessages($messages),
        ]), $options);

        return $this->applyMode($request, $mode, $tools, $toolChoice, $responseFormat);
    }

    // OVERRIDES - HELPERS ///////////////////////////////////

    protected function applyMode(
        array $request,
        Mode $mode,
        array $tools,
        string|array $toolChoice,
        array $responseFormat
    ) : array {
        switch($mode) {
            case Mode::Tools:
                $request['tools'] = $this->removeDisallowedEntries($tools);
                $request['tool_choice'] = $this->toToolChoice($tools, $toolChoice);
                break;
            case Mode::Json:
                $request['response_format'] = $responseFormat;
                break;
            case Mode::JsonSchema:
                $request['response_format'] = [
                    'type' => 'json_object',
                    'schema' => $responseFormat['json_schema']['schema'],
                ];
                break;
        }
        return $request;
    }

    protected function removeDisallowedEntries(array $jsonSchema) : array {
        return Arrays::removeRecursively($jsonSchema, [
            'title',
            'x-php-class',
            'additionalProperties',
        ]);
    }

    private function toToolChoice(array $tools, array|string $toolChoice) : array|string {
        return match(true) {
            empty($tools) => '',
            empty($toolChoice) => 'auto',
            is_array($toolChoice) => [
                'type' => 'function',
                'name' => $toolChoice['function']['name'],
            ],
            default => $toolChoice,
        };
    }
}
