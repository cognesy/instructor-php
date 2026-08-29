<?php

declare(strict_types=1);

namespace Cognesy\Tell\Configuration;

use JsonException;
use RuntimeException;

/** Strict, secret-free policy-default overlay shared by project and user scopes. */
final readonly class TellPolicyDefaults
{
    private const string SCHEMA = 'tell.execution-defaults.v1';

    /** @return array<string, int> */
    public static function fromFile(string $path): array {
        if (!file_exists($path)) {
            return [];
        }
        if (is_link($path) || !is_file($path)) {
            throw new RuntimeException("Tell policy defaults are not a safe regular file: {$path}");
        }
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException("Tell policy defaults could not be read: {$path}");
        }
        try {
            $data = json_decode($bytes, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException("Tell policy defaults are invalid JSON: {$path}", previous: $error);
        }
        if (!is_array($data) || array_is_list($data) || array_keys($data) !== ['schema', 'values']
            || $data['schema'] !== self::SCHEMA || !is_array($data['values']) || array_is_list($data['values'])) {
            throw new RuntimeException("Tell policy defaults have an unsupported shape: {$path}");
        }
        $values = $data['values'];
        foreach ($values as $key => $value) {
            if (!in_array($key, array_keys(TellExecutionPolicy::defaults()->values()), true) || !is_int($value)) {
                throw new RuntimeException("Tell policy defaults contain an invalid value: {$path}");
            }
        }
        TellExecutionPolicy::resolve([], [], $values);

        /** @var array<string, int> $values */
        return $values;
    }
}
