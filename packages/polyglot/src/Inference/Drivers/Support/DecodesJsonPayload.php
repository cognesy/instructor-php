<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Support;

use JsonException;
use RuntimeException;

/**
 * Shared JSON payload decoding for response adapters.
 *
 * Four adapters (Anthropic, OpenAI, OpenResponses, Gemini) carried
 * byte-identical copies of this method. They do not share a supertype --
 * OpenAIResponseAdapter is a base for many drivers, while the other three
 * implement CanTranslateInferenceResponse directly -- so this is a trait
 * rather than a base class, to avoid inventing an inheritance edge that
 * does not otherwise exist.
 */
trait DecodesJsonPayload
{
    /**
     * @return array<string,mixed>
     */
    protected function decodeJsonData(string $payload, string $context): array {
        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException($context . ' is not valid JSON.', previous: $e);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException($context . ' must decode to an object or array.');
        }

        return $decoded;
    }
}
