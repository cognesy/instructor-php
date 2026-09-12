<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\SambaNova;

use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use InvalidArgumentException;
class SambaNovaBodyFormat extends OpenAICompatibleBodyFormat
{
    // INTERNAL ///////////////////////////////////////////////

    #[\Override]
    protected function toJsonSchemaResponseFormat(ResponseFormat $responseFormat) : array {
        throw new InvalidArgumentException(
            'SambaNova cannot render JSON Schema; request JSON Object explicitly '
            . 'or enable an evidenced lossy fallback before rendering.',
        );
    }
}
