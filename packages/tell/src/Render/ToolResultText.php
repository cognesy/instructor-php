<?php

declare(strict_types=1);

namespace Cognesy\Tell\Render;

use JsonException;

/**
 * The readable text inside a tool result envelope.
 *
 * Tell's coding tools return a structured envelope whose payload is a single
 * text field. A trace shows that text, because the envelope around it repeats
 * what the trace already states in its own status marker.
 */
final class ToolResultText
{
    public static function from(mixed $result): string {
        if (is_string($result)) {
            return $result;
        }
        if (!is_array($result)) {
            return self::encode($result);
        }
        $data = $result['data'] ?? null;
        if (is_array($data) && is_string($data['text'] ?? null)) {
            return $data['text'];
        }
        if (is_string($data)) {
            return $data;
        }

        return self::encode($data ?? $result);
    }

    /** @return array{code: string, message: string}|null */
    public static function error(mixed $result): ?array {
        if (!is_array($result) || !is_array($result['error'] ?? null)) {
            return null;
        }
        $error = $result['error'];

        return [
            'code' => is_string($error['code'] ?? null) ? $error['code'] : 'error',
            'message' => is_string($error['message'] ?? null) ? $error['message'] : '',
        ];
    }

    private static function encode(mixed $value): string {
        if ($value === null) {
            return '';
        }
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return '<unencodable result>';
        }
    }
}
