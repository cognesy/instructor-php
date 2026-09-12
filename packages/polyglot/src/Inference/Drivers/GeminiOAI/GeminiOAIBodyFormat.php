<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\GeminiOAI;

use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use InvalidArgumentException;
class GeminiOAIBodyFormat extends OpenAICompatibleBodyFormat
{
    // INTERNAL /////////////////////////////////////////////

    #[\Override]
    protected function toJsonSchemaResponseFormat(ResponseFormat $responseFormat) : array {
        throw new InvalidArgumentException(
            'Gemini OpenAI-compatible API cannot render JSON Schema; request JSON Object '
            . 'explicitly or enable an evidenced lossy fallback before rendering.',
        );
    }
}

// Add support for:
// "reasoning_effort": "low", "medium", "high", "none"
// "extra_body": {"google": {"cached_content": {...}}}
