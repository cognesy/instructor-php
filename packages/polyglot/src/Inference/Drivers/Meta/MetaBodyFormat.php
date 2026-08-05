<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Meta;

use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
class MetaBodyFormat extends OpenAICompatibleBodyFormat
{
    // INTERNAL //////////////////////////////////////////////

    /**
     * Meta (via OpenRouter) only speaks json_schema, so plain JSON mode is sent as a schema
     * payload too. The schema envelope itself is the base's — the two injected handlers this
     * replaced were identical to each other AND to the base, spelled out twice.
     */
    #[\Override]
    protected function toJsonObjectResponseFormat(ResponseFormat $responseFormat) : array {
        return $this->toJsonSchemaResponseFormat($responseFormat);
    }
}
