<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\A21;

use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
class A21BodyFormat extends OpenAICompatibleBodyFormat
{
    // INTERNAL ///////////////////////////////////////////////

    // A21 API supports json_object and text. It does not accept a schema at all, so schema
    // mode degrades to plain JSON rather than sending a payload A21 would reject.
    #[\Override]
    protected function toJsonSchemaResponseFormat(ResponseFormat $responseFormat) : array {
        return $this->toJsonObjectResponseFormat($responseFormat);
    }
}
