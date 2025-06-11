<?php

namespace Cognesy\Polyglot\Inference\Drivers\Fireworks;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

class FireworksBodyFormat extends OpenAICompatibleBodyFormat
{
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
                    'type' => 'json_object',
                    'schema' => $schema,
                ];
                break;
            default:
                $result = [];
        }

        return $result;
    }
}