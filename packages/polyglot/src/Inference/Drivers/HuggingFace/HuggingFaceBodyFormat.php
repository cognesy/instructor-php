<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\HuggingFace;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;

class HuggingFaceBodyFormat extends OpenAICompatibleBodyFormat
{
    // CAPABILITIES /////////////////////////////////////////

    #[\Override]
    protected function supportsNonTextResponseForTools(InferenceRequest $request) : bool {
        return false;
    }

    // INTERNAL /////////////////////////////////////////////

    // HuggingFace takes the bare schema under `value`, with no name or strict flag, rather
    // than the base's `json_schema` envelope.
    #[\Override]
    protected function toJsonSchemaResponseFormat(ResponseFormat $responseFormat) : array {
        return [
            'type' => 'json_schema',
            'value' => $this->removeDisallowedEntries($responseFormat->schema()),
        ];
    }
}
