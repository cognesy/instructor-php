<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\HuggingFace;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;

class HuggingFaceBodyFormat extends OpenAICompatibleBodyFormat
{
    // CAPABILITIES /////////////////////////////////////////

    #[\Override]
    protected function supportsNonTextResponseForTools(InferenceRequest $request) : bool {
        return false;
    }

    // INTERNAL /////////////////////////////////////////////

    #[\Override]
    protected function toResponseFormat(InferenceRequest $request) : array {
        $type = $this->toResponseFormatType($request);
        if ($type === null) {
            return [];
        }

        $responseFormat = $request->responseFormat()
            ->withToJsonObjectHandler(fn() => ['type' => 'json_object'])
            ->withToJsonSchemaHandler(fn() => [
                'type' => 'json_schema',
                'value' => $this->removeDisallowedEntries($request->responseFormat()->schema()),
            ]);

        return $this->renderResponseFormatForType($responseFormat, $type);
    }
}
