<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\CohereV2;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Arrays;

class CohereV2BodyFormat extends OpenAICompatibleBodyFormat
{
    #[\Override]
    public function toRequestBody(InferenceRequest $request) : array {
        $requestBody = parent::toRequestBody($request);

        if (array_key_exists('max_completion_tokens', $requestBody)
            && !array_key_exists('max_tokens', $requestBody)
        ) {
            $requestBody['max_tokens'] = $requestBody['max_completion_tokens'];
        }
        unset($requestBody['max_completion_tokens']);
        // Cohere V2 does not support some OpenAI params, so we unset it
        unset($requestBody['tool_choice']);
        unset($requestBody['parallel_tool_calls']);
        unset($requestBody['stream_options']);

        return $requestBody;
    }

    // CAPABILITIES /////////////////////////////////////////

    #[\Override]
    protected function supportsNonTextResponseForTools(InferenceRequest $request) : bool {
        return false;
    }

    // INTERNAL //////////////////////////////////////////////

    #[\Override]
    protected function toResponseFormat(InferenceRequest $request) : array {
        if (!$request->hasResponseFormat()) {
            return [];
        }

        $mode = $request->outputMode();
        // Cohere V2 API supports: json_object with schema, text
        $responseFormat = $request->responseFormat()
            ->withToJsonObjectHandler(fn() => [
                'type' => 'json_object',
                'schema' => $this->removeDisallowedEntries($request->responseFormat()->schema()),
            ])
            ->withToJsonSchemaHandler(fn() => [
                'type' => 'json_object',
                'schema' => $this->removeDisallowedEntries($request->responseFormat()->schema()),
            ]);

        return $responseFormat->as($mode);
    }

    #[\Override]
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