<?php

namespace Cognesy\Polyglot\LLM\Drivers\HuggingFace;

use Cognesy\Polyglot\LLM\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Polyglot\LLM\InferenceRequest;

class HuggingFaceBodyFormat extends OpenAICompatibleBodyFormat
{
    protected function toResponseFormat(InferenceRequest $request) : array {
        $mode = $this->toResponseFormatMode($request);
        switch ($mode) {
            case OutputMode::Json:
                [$schema, $schemaName, $schemaStrict] = $this->toSchemaData($request);
                $result = ['type' => 'json', 'value' => $schema];
                break;
            case OutputMode::JsonSchema:
                [$schema, $schemaName, $schemaStrict] = $this->toSchemaData($request);
                $result = ['type' => 'json_object', 'value' => $schema];
                break;
            case OutputMode::Text:
            case OutputMode::MdJson:
                $result = [];
                break;
            default:
                $result = [];
        }

        return $result;
    }
}
