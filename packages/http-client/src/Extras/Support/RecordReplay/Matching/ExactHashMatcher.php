<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay\Matching;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Extras\Support\RecordReplay\SensitiveDataNames;
use JsonException;
use stdClass;

/**
 * Default request matcher using a versioned, length-delimited SHA-256 input.
 *
 * Authorization and transport-only options are excluded. JSON request bodies
 * are canonicalized only when their content type explicitly says JSON; all
 * other bodies remain byte-exact.
 */
final class ExactHashMatcher implements RequestMatcher
{
    public const FINGERPRINT_VERSION = 'http-request-fingerprint-v2';

    private const RESPONSE_SHAPING_HEADERS = ['accept', 'content-type'];

    #[\Override]
    public function fingerprint(HttpRequest $request): string
    {
        [$bodyType, $body] = $this->bodyProjection($request);
        $fields = [
            'version' => self::FINGERPRINT_VERSION,
            'method' => strtoupper($request->method()),
            'url' => self::normalizeUrl($request->url()),
            'streamed' => $request->isStreamed() ? '1' : '0',
            'body-type' => $bodyType,
            'body' => $body,
            'headers' => self::shapingHeaders($request->headers()),
        ];

        return hash('sha256', self::lengthDelimited($fields));
    }

    public function fingerprintVersion(): string
    {
        return self::FINGERPRINT_VERSION;
    }

    /** @param array<string, mixed> $headers */
    private static function shapingHeaders(array $headers): string
    {
        $selected = [];
        foreach ($headers as $name => $value) {
            $normalizedName = strtolower((string) $name);
            if (!in_array($normalizedName, self::RESPONSE_SHAPING_HEADERS, true)) {
                continue;
            }
            $values = is_array($value) ? array_map(strval(...), $value) : [(string) $value];
            $values = array_map(static fn(string $item): string => trim($item), $values);
            $selected[$normalizedName] = implode(',', $values);
        }
        ksort($selected);

        return self::lengthDelimited($selected);
    }

    /** @return array{0: string, 1: string} */
    private function bodyProjection(HttpRequest $request): array
    {
        $body = $request->body()->toString();
        if (!self::hasJsonContentType($request->headers())) {
            return ['raw', $body];
        }

        try {
            $decoded = json_decode($body, false, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            $canonical = json_encode(
                self::canonicalizeJson($decoded),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (JsonException) {
            return ['raw', $body];
        }

        return ['json', $canonical];
    }

    /** @param array<string, mixed> $headers */
    private static function hasJsonContentType(array $headers): bool
    {
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) !== 'content-type') {
                continue;
            }
            $values = is_array($value) ? $value : [$value];
            foreach ($values as $item) {
                if (is_string($item) && str_contains(strtolower($item), 'json')) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function canonicalizeJson(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $properties = get_object_vars($value);
            ksort($properties);
            foreach ($properties as $name => $property) {
                $properties[$name] = self::canonicalizeJson($property);
            }

            return (object) $properties;
        }
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $index => $item) {
            $value[$index] = self::canonicalizeJson($item);
        }

        return $value;
    }

    /** @param array<string, string> $fields */
    private static function lengthDelimited(array $fields): string
    {
        $encoded = '';
        foreach ($fields as $name => $value) {
            $encoded .= strlen($name) . ':' . $name . strlen($value) . ':' . $value;
        }

        return $encoded;
    }

    private static function normalizeUrl(string $url): string
    {
        $names = implode('|', array_map('preg_quote', SensitiveDataNames::queryParameters()));

        return preg_replace_callback(
            '/([?&])(' . $names . ')=([^&#]*)/i',
            static fn(array $match): string => $match[1] . strtolower($match[2]) . '=',
            $url,
        ) ?? $url;
    }
}
