<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenAICompatible;

use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIBodyFormat;

class OpenAICompatibleBodyFormat extends OpenAIBodyFormat
{
    /**
     * Coerces the loosely-typed values OpenAI-compatible providers accept for
     * boolean request flags. Qwen and Glm carried byte-identical copies.
     */
    protected function toBoolean(mixed $value): bool {
        return match (true) {
            is_bool($value) => $value,
            is_int($value) => $value !== 0,
            is_float($value) => $value !== 0.0,
            is_string($value) => in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on', 'enabled'], true),
            default => (bool) $value,
        };
    }
}
