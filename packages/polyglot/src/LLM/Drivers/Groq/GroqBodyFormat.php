<?php

namespace Cognesy\Polyglot\LLM\Drivers\Groq;

use Cognesy\Polyglot\LLM\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Polyglot\LLM\InferenceRequest;

class GroqBodyFormat extends OpenAICompatibleBodyFormat
{
    public function toRequestBody(InferenceRequest $request) : array {
        $requestBody = parent::toRequestBody($request);

        // max_tokens is deprecated in Groq, use max_completion_tokens instead
        $requestBody['max_completion_tokens'] = $requestBody['max_tokens'];
        unset($requestBody['max_tokens']);

        return $requestBody;
    }

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
