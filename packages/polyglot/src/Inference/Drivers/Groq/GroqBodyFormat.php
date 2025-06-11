<?php

namespace Cognesy\Polyglot\Inference\Drivers\Groq;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

class GroqBodyFormat extends OpenAICompatibleBodyFormat
{
    public function toRequestBody(InferenceRequest $request) : array {
        $requestBody = parent::toRequestBody($request);

        // max_tokens is deprecated in Groq, use max_completion_tokens instead
        $requestBody['max_completion_tokens'] = $requestBody['max_tokens'];
        unset($requestBody['max_tokens']);

        return $requestBody;
    }

    // CAPABILITIES ///////////////////////////////////////////

    protected function supportsNonTextResponseForTools(InferenceRequest $request) : bool {
        return false;
    }

    // INTERNAL ///////////////////////////////////////////////

    protected function toResponseFormat(InferenceRequest $request) : array {
        $mode = $this->toResponseFormatMode($request);
        switch ($mode) {
            case OutputMode::Json:
                $result = ['type' => 'json_object'];
                break;
            case OutputMode::Text:
            case OutputMode::MdJson:
                $result = ['type' => 'text'];
                break;
            case OutputMode::JsonSchema:
                [$schema, $schemaName, $schemaStrict] = $this->toSchemaData($request);
                $result = [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => $schemaName,
                        'schema' => $schema,
                        'strict' => $schemaStrict,
                    ]];
                break;
            default:
                $result = [];
        }
        return $result;
    }
}
