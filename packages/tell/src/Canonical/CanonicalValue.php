<?php

declare(strict_types=1);

namespace Cognesy\Tell\Canonical;

use stdClass;

final class CanonicalValue
{
    /** @var list<string> */
    private const array FORBIDDEN_KEYS = [
        'apiKey',
        'api_key',
        'authorization',
        'credentials',
        'headers',
        'latency',
        'provider',
        'providerRequest',
        'providerResponse',
        'rawRequest',
        'rawResponse',
        'rendered',
        'renderedOutput',
        'request',
        'response',
        'secret',
        'timestamp',
        'token',
        'usage',
    ];

    private function __construct() {}

    public static function normalize(mixed $value, ?string $field = null): mixed
    {
        if ($value instanceof stdClass) {
            return self::normalize(get_object_vars($value), $field);
        }
        if ($value === null || is_bool($value) || is_int($value)) {
            return $value;
        }
        if (is_string($value)) {
            if (preg_match('//u', $value) !== 1) {
                throw new CanonicalValidationException('Canonical values must use valid UTF-8.');
            }
            if (in_array($field, ['path', 'file', 'directory'], true) && str_starts_with($value, '/')) {
                throw new CanonicalValidationException('Canonical tool values cannot contain absolute filesystem paths.');
            }

            return $value;
        }
        if (is_float($value)) {
            throw new CanonicalValidationException('Canonical values do not permit floating-point numbers.');
        }
        if (! is_array($value)) {
            throw new CanonicalValidationException('Canonical values must be null, booleans, integers, UTF-8 strings, lists, or objects.');
        }

        if (array_is_list($value)) {
            return array_map(static fn (mixed $item): mixed => self::normalize($item), $value);
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (! is_string($key) || preg_match('//u', $key) !== 1) {
                throw new CanonicalValidationException('Canonical object keys must be UTF-8 strings.');
            }
            if (in_array($key, self::FORBIDDEN_KEYS, true)) {
                throw new CanonicalValidationException("Canonical values cannot include {$key}.");
            }
            $normalized[$key] = self::normalize($item, $key);
        }
        ksort($normalized, SORT_STRING);

        return $normalized;
    }
}
