<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Fireworks;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
class FireworksBodyFormat extends OpenAICompatibleBodyFormat
{
    // CAPABILITIES ///////////////////////////////////////////

    #[\Override]
    protected function supportsNonTextResponseForTools(InferenceRequest $request) : bool {
        return false;
    }

    // INTERNAL ///////////////////////////////////////////////

    #[\Override]
    protected function toResponseFormat(InferenceRequest $request) : array {
        $type = $this->toResponseFormatType($request);
        if ($type === null) {
            return [];
        }

        // Fireworks API supports: json_object with optional schema, text
        $responseFormat = $request->responseFormat()
            ->withToJsonObjectHandler(fn() => ['type' => 'json_object'])
            ->withToJsonSchemaHandler(fn() => [
                'type' => 'json_object',
                'schema' => $this->removeDisallowedEntries($request->responseFormat()->schema()),
            ]);

        return $this->renderResponseFormatForType($responseFormat, $type);
    }
}
