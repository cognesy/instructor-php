<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Workspace\Branch;

use InvalidArgumentException;

/** Domain policy shared by every branch-configuration storage provider. */
final readonly class BranchConfigurationPolicy
{
    private const int MAX_TOOL_NAMES = 50;

    /** @var array<string, array{type: 'enum'|'int'|'list'|'string', min?: int, max?: int, values?: list<string>}> */
    private const array KEYS = [
        'connection' => ['type' => 'string'],
        'model' => ['type' => 'string'],
        'reasoningEffort' => ['type' => 'enum', 'values' => ['minimal', 'low', 'medium', 'high', 'xhigh', 'max']],
        'output' => ['type' => 'enum', 'values' => ['toon', 'text', 'human', 'json', 'events']],
        'tools' => ['type' => 'list'],
        'maxRetries' => ['type' => 'int', 'min' => 0, 'max' => 10],
        'timeoutMs' => ['type' => 'int', 'min' => 1, 'max' => 3_600_000],
        'maxOutputChars' => ['type' => 'int', 'min' => 1, 'max' => 1_000_000],
        'maxToolOutputChars' => ['type' => 'int', 'min' => 1, 'max' => 250_000],
        'maxToolCalls' => ['type' => 'int', 'min' => 0, 'max' => 1_000],
        'maxSpillBytes' => ['type' => 'int', 'min' => 0, 'max' => 5_000_000],
        'maxStubBytes' => ['type' => 'int', 'min' => 0, 'max' => 100_000],
    ];

    /** @var array<string, int|list<string>|string> */
    private const array DEFAULTS = [
        'connection' => 'openai',
        'output' => 'human',
        'tools' => [],
        'maxRetries' => 0,
        'timeoutMs' => 30_000,
        'maxOutputChars' => 200_000,
        'maxToolOutputChars' => 40_000,
        'maxToolCalls' => 100,
        'maxSpillBytes' => 200_000,
        'maxStubBytes' => 2_000,
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::KEYS);
    }

    /** @return array<string, int|list<string>|string> */
    public static function defaults(): array
    {
        return self::DEFAULTS;
    }

    public static function assertValue(mixed $key, mixed $value): void
    {
        self::assertKey($key);
        $spec = self::KEYS[$key];
        if ($spec['type'] === 'string') {
            if (!is_string($value) || trim($value) === '' || self::containsSecret($value)) {
                throw new InvalidArgumentException("Tell branch config {$key} must be a non-secret non-empty string.");
            }

            return;
        }
        if ($spec['type'] === 'int') {
            if (!is_int($value) || $value < ($spec['min'] ?? 0) || $value > ($spec['max'] ?? PHP_INT_MAX)) {
                throw new InvalidArgumentException("Tell branch config {$key} has an invalid range.");
            }

            return;
        }
        if ($spec['type'] === 'enum') {
            if (!is_string($value) || !in_array($value, $spec['values'] ?? [], true)) {
                $allowed = implode(', ', $spec['values'] ?? []);
                throw new InvalidArgumentException("Tell branch config {$key} must be one of: {$allowed}.");
            }

            return;
        }
        if (!is_array($value) || !array_is_list($value) || count($value) > self::MAX_TOOL_NAMES) {
            throw new InvalidArgumentException("Tell branch config {$key} must be a bounded list of non-empty strings.");
        }
        foreach ($value as $item) {
            if (!is_string($item) || trim($item) === '' || self::containsSecret($item)) {
                throw new InvalidArgumentException("Tell branch config {$key} must be a bounded list of non-secret non-empty strings.");
            }
        }
    }

    public static function assertKey(mixed $key): void
    {
        if (!is_string($key) || !array_key_exists($key, self::KEYS)) {
            $display = is_string($key) ? $key : get_debug_type($key);
            throw new InvalidArgumentException("Unknown Tell branch config key: {$display}");
        }
    }

    private static function containsSecret(string $value): bool
    {
        return preg_match('/(?:api[_-]?key|token|password|secret|authorization|bearer\s+|:\/\/[^\/\s:]+:[^@\s]+@)/i', $value) === 1;
    }
}
