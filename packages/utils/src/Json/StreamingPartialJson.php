<?php declare(strict_types=1);

namespace Cognesy\Utils\Json;

use JsonException;
use Throwable;

final class StreamingPartialJson
{
    /**
     * @return array<array-key, mixed>|null
     */
    public static function toArray(string $buffer): ?array
    {
        $trimmed = trim($buffer);
        if ($trimmed === '') {
            return null;
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (JsonException) {
        }

        try {
            $parsed = (new PartialJsonParser())->parse($trimmed);
            return is_array($parsed) ? $parsed : null;
        } catch (Throwable) {
            return null;
        }
    }
}
