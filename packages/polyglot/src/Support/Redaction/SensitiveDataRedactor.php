<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Support\Redaction;

/**
 * Shared redaction rules for telemetry/event payloads that may carry
 * credentials (headers, config arrays, query strings).
 */
final class SensitiveDataRedactor
{
    public const MASK = '[REDACTED]';

    private const SENSITIVE_KEYS = [
        'apikey', 'authorization', 'proxyauthorization', 'token',
        'accesstoken', 'refreshtoken', 'secret', 'password', 'cookie', 'setcookie',
    ];

    public static function isSensitiveKey(string $key): bool {
        $normalized = strtolower(str_replace(['-', '_'], '', $key));

        return match (true) {
            in_array($normalized, self::SENSITIVE_KEYS, true) => true,
            str_contains($normalized, 'apikey') => true,
            str_contains($normalized, 'authorization') => true,
            str_contains($normalized, 'cookie') => true,
            default => str_contains($normalized, 'token')
                || str_contains($normalized, 'secret')
                || str_contains($normalized, 'password'),
        };
    }

    /**
     * Recursively masks values under sensitive keys.
     *
     * @param array<array-key,mixed> $data
     * @return array<array-key,mixed>
     */
    public static function redactValues(array $data): array {
        $redacted = [];
        foreach ($data as $key => $value) {
            $redacted[$key] = match (true) {
                self::isSensitiveKey((string) $key) => self::MASK,
                is_array($value) => self::redactValues($value),
                default => $value,
            };
        }

        return $redacted;
    }

    /**
     * Masks header values whose name is sensitive. Header values are never
     * recursed into; only the top-level value is masked.
     *
     * @param array<array-key,mixed> $headers
     * @return array<array-key,mixed>
     */
    public static function redactHeaders(array $headers): array {
        $redacted = [];
        foreach ($headers as $name => $value) {
            $redacted[$name] = self::isSensitiveKey((string) $name) ? self::MASK : $value;
        }

        return $redacted;
    }

    /**
     * Returns the URL with any userinfo and sensitive query parameters masked.
     * Non-sensitive query parameters and the rest of the URL are preserved.
     */
    public static function redactUrl(string $url): string {
        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        if (isset($parts['user']) && $parts['user'] !== '') {
            $parts['user'] = self::MASK;
        }
        if (isset($parts['pass']) && $parts['pass'] !== '') {
            $parts['pass'] = self::MASK;
        }
        if (isset($parts['query'])) {
            $parts['query'] = self::redactQuery($parts['query']);
        }

        return self::buildUrl($parts);
    }

    /**
     * Replaces occurrences of a raw URL inside a message with its redacted
     * form, so exception text cannot leak credentials embedded in the URL.
     */
    public static function redactUrlInText(string $text, string $url): string {
        if ($url === '') {
            return $text;
        }

        return str_replace($url, self::redactUrl($url), $text);
    }

    /**
     * Redacts every http(s) URL found anywhere in a free-text message (e.g. an
     * exception message whose exact URL is not known ahead of time), masking
     * userinfo and sensitive query parameters while preserving trailing
     * sentence punctuation.
     */
    public static function redactMessage(string $message): string {
        $sanitized = preg_replace_callback(
            '/https?:\/\/[^\s]+/i',
            static fn(array $matches): string => self::redactUrlWithTrailingPunctuation($matches[0]),
            $message,
        );

        return $sanitized ?? $message;
    }

    private static function redactUrlWithTrailingPunctuation(string $url): string {
        $suffix = '';
        while ($url !== '' && in_array(substr($url, -1), ['.', ',', ';', ')', ']'], true)) {
            $suffix = substr($url, -1) . $suffix;
            $url = substr($url, 0, -1);
        }

        return self::redactUrl($url) . $suffix;
    }

    /**
     * Produces a credential-safe summary of a configuration/data array for use
     * in exception diagnostics: reports each field name and its received type,
     * never any value. Nested arrays are summarized one level deep.
     *
     * @param array<array-key,mixed> $data
     */
    public static function summarizeFieldTypes(array $data): string {
        $summary = [];
        foreach ($data as $key => $value) {
            $summary[(string) $key] = get_debug_type($value);
        }

        $json = json_encode($summary, JSON_UNESCAPED_SLASHES);

        return $json === false ? '{}' : $json;
    }

    private static function redactQuery(string $query): string {
        $segments = explode('&', $query);
        $redacted = [];

        foreach ($segments as $segment) {
            if ($segment === '') {
                $redacted[] = $segment;
                continue;
            }

            [$rawKey] = array_pad(explode('=', $segment, 2), 2, null);
            $decodedKey = urldecode((string) $rawKey);
            if (!self::isSensitiveKey($decodedKey)) {
                $redacted[] = $segment;
                continue;
            }

            $redacted[] = $rawKey . '=' . rawurlencode(self::MASK);
        }

        return implode('&', $redacted);
    }

    /**
     * @param array<string,mixed> $parts
     */
    private static function buildUrl(array $parts): string {
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $auth = $user !== '' ? $user . $pass . '@' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $scheme . $auth . $host . $port . $path . $query . $fragment;
    }
}
