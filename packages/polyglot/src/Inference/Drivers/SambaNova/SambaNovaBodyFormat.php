<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\SambaNova;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
class SambaNovaBodyFormat extends OpenAICompatibleBodyFormat
{
    // CAPABILITIES ///////////////////////////////////////////

    #[\Override]
    protected function supportsNonTextResponseForTools(InferenceRequest $request) : bool {
        return false;
    }

    // INTERNAL ///////////////////////////////////////////////

    // SambaNova API supports json_object but no schema, so schema mode degrades to plain JSON.
    #[\Override]
    protected function toJsonSchemaResponseFormat(ResponseFormat $responseFormat) : array {
        return $this->toJsonObjectResponseFormat($responseFormat);
    }
}
