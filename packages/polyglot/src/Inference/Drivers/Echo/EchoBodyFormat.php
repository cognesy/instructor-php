<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Echo;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use InvalidArgumentException;

/**
 * Fulcrum Echo - OpenAI-compatible chat completions with a mandatory top-level
 * `persona` (the writer whose voice Echo uses). Persona comes from preset or
 * request options; the API rejects requests without it, so we fail before sending.
 */
class EchoBodyFormat extends OpenAICompatibleBodyFormat
{
    #[\Override]
    public function toRequestBody(InferenceRequest $request): array
    {
        $requestBody = parent::toRequestBody($request);
        $persona = $requestBody['persona'] ?? null;

        return match (true) {
            is_string($persona) && trim($persona) !== '' => $requestBody,
            default => throw new InvalidArgumentException(
                "Echo requires a non-empty string 'persona' option - the name of the writer whose voice to use.",
            ),
        };
    }

    #[\Override]
    protected function normalizeTokenLimits(array $requestBody): array
    {
        return $requestBody; // Echo uses max_tokens as-is
    }
}
