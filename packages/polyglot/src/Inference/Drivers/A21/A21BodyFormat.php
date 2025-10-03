<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\A21;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

class A21BodyFormat extends OpenAICompatibleBodyFormat
{
    // INTERNAL ///////////////////////////////////////////////

    #[\Override]
    protected function toResponseFormat(InferenceRequest $request) : array {
        if (!$request->hasResponseFormat()) {
            return [];
        }

        $mode = $request->outputMode();
        // A21 API supports: json_object, text
        // Does not support json_schema with schema
        $responseFormat = $request->responseFormat()
            ->withToJsonObjectHandler(fn() => ['type' => 'json_object'])
            ->withToJsonSchemaHandler(fn() => ['type' => 'json_object']); // Falls back to json_object for schema mode

        return $responseFormat->as($mode);
    }
}