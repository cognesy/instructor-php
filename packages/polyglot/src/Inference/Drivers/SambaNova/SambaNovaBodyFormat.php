<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\SambaNova;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

class SambaNovaBodyFormat extends OpenAICompatibleBodyFormat
{
    // CAPABILITIES ///////////////////////////////////////////

    #[\Override]
    protected function supportsNonTextResponseForTools(InferenceRequest $request) : bool {
        return false;
    }

    // INTERNAL ///////////////////////////////////////////////

    #[\Override]
    protected function toResponseFormat(InferenceRequest $request) : array {
        if (!$request->hasResponseFormat()) {
            return [];
        }

        $mode = $request->outputMode();
        // SambaNova API supports: json_object (no schema support)
        $responseFormat = $request->responseFormat()
            ->withToJsonObjectHandler(fn() => ['type' => 'json_object'])
            ->withToJsonSchemaHandler(fn() => ['type' => 'json_object']); // Falls back to json_object

        return $responseFormat->as($mode);
    }
}