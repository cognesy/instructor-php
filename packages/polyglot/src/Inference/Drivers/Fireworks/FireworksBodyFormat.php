<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Fireworks;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
class FireworksBodyFormat extends OpenAICompatibleBodyFormat
{
    // CAPABILITIES ///////////////////////////////////////////

    #[\Override]
    protected function supportsNonTextResponseForTools(InferenceRequest $request) : bool {
        return false;
    }

    // INTERNAL ///////////////////////////////////////////////

    // Fireworks takes a schema, but hangs it off json_object rather than a json_schema type.
    #[\Override]
    protected function toJsonSchemaResponseFormat(ResponseFormat $responseFormat) : array {
        return [
            'type' => 'json_object',
            'schema' => $this->removeDisallowedEntries($responseFormat->schema()),
        ];
    }
}
