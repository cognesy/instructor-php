<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Meta;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
class MetaBodyFormat extends OpenAICompatibleBodyFormat
{
    // INTERNAL //////////////////////////////////////////////

    #[\Override]
    protected function toResponseFormat(InferenceRequest $request) : array {
        $type = $this->toResponseFormatType($request);
        if ($type === null) {
            return [];
        }

        // Meta API (via OpenRouter) supports: json_schema
        $responseFormat = $request->responseFormat()
            ->withToJsonObjectHandler(fn() => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $request->responseFormat()->schemaName(),
                    'schema' => $this->removeDisallowedEntries($request->responseFormat()->schema()),
                    'strict' => $request->responseFormat()->strict(),
                ],
            ])
            ->withToJsonSchemaHandler(fn() => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $request->responseFormat()->schemaName(),
                    'schema' => $this->removeDisallowedEntries($request->responseFormat()->schema()),
                    'strict' => $request->responseFormat()->strict(),
                ],
            ]);

        return $this->renderResponseFormatForType($responseFormat, $type);
    }
}
