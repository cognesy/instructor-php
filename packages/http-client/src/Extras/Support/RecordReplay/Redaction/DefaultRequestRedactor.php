<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay\Redaction;

/**
 * Masks credential-bearing request headers. The header set is provider-aware:
 * different providers carry the API key under different names (Authorization for
 * most, x-api-key for Anthropic, x-goog-api-key for Gemini) — a redactor keyed
 * only on Authorization would leak Gemini's key. Header matching is
 * case-insensitive; matched values are replaced with a fixed mask.
 *
 * Bodies are left untouched by default (for LLM providers the credential lives in
 * headers, and the body participates in request matching); redact known sensitive
 * body fields via a custom RequestRedactor when a provider puts secrets there.
 */
final class DefaultRequestRedactor implements RequestRedactor
{
    public const MASK = '[REDACTED]';

    /** Lower-cased header names whose values must never reach disk. */
    private const SENSITIVE_HEADERS = [
        'authorization',
        'proxy-authorization',
        'x-api-key',
        'x-goog-api-key',
        'api-key',
        'cookie',
        'set-cookie',
    ];

    /** Query-parameter names whose values are credentials (e.g. Gemini's `?key=`). */
    public const SENSITIVE_QUERY_PARAMS = ['key', 'api_key', 'apikey', 'access_token', 'token'];

    #[\Override]
    public function redact(array $requestData): array {
        $headers = $requestData['headers'] ?? null;
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (in_array(strtolower((string) $name), self::SENSITIVE_HEADERS, true)) {
                    $headers[$name] = self::MASK;
                }
            }
            $requestData['headers'] = $headers;
        }

        // Some providers (e.g. Gemini) pass the API key as a URL query parameter,
        // not a header — mask those values too so no key reaches disk.
        if (isset($requestData['url']) && is_string($requestData['url'])) {
            $requestData['url'] = self::redactUrl($requestData['url']);
        }

        return $requestData;
    }

    private static function redactUrl(string $url): string {
        $names = implode('|', array_map('preg_quote', self::SENSITIVE_QUERY_PARAMS));
        return preg_replace_callback(
            '/([?&](?:' . $names . ')=)([^&#]*)/i',
            static fn(array $m): string => $m[1] . rawurlencode(self::MASK),
            $url,
        ) ?? $url;
    }
}
