<?php

namespace Cognesy\Polyglot\LLM\Drivers\CohereV2;

use Cognesy\Polyglot\LLM\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Polyglot\LLM\InferenceRequest;
use Cognesy\Utils\Arrays;

class CohereV2BodyFormat extends OpenAICompatibleBodyFormat
{
    public function toRequestBody(InferenceRequest $request) : array {
        $requestBody = parent::toRequestBody($request);

        // Cohere V2 does not support some OpenAI params, so we unset it
        unset($requestBody['tool_choice']);
        unset($requestBody['parallel_tool_calls']);
        unset($requestBody['stream_options']);

        return $requestBody;
    }

    // INTERNAL //////////////////////////////////////////////

    protected function toResponseFormat(InferenceRequest $request) : array {
        $mode = $this->toResponseFormatMode($request);
        switch ($mode) {
            case OutputMode::Json:
            case OutputMode::JsonSchema:
                [$schema, $schemaName, $schemaStrict] = $this->toSchemaData($request);
                $result = [
                    'type' => 'json_object',
                    'json_schema' => $schema,
                ];
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

    protected function removeDisallowedEntries(array $jsonSchema) : array {
        return Arrays::removeRecursively(
            array: $jsonSchema,
            keys: [
                'x-title',
                'x-php-class',
                'additionalProperties',
            ],
        );
    }
}