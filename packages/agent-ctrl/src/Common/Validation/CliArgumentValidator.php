<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Common\Validation;

use InvalidArgumentException;

final class CliArgumentValidator
{
    private const SAFE_TOKEN_PATTERN = '/^[A-Za-z0-9._:\\/-]+$/';

    public static function validateModel(?string $model): void
    {
        self::validateOptionalToken($model, 'model');
    }

    public static function validateSessionId(?string $sessionId, string $fieldName = 'sessionId'): void
    {
        self::validateOptionalToken($sessionId, $fieldName);
    }

    public static function validateExistingFile(?string $path, string $fieldName, ?string $baseDir = null): void
    {
        if ($path === null || trim($path) === '') {
            return;
        }

        $resolvedPath = self::resolvePath($path, $baseDir);
        if (is_file($resolvedPath)) {
            return;
        }

        throw new InvalidArgumentException("{$fieldName} does not exist or is not a file: {$path}");
    }

    /**
     * @param list<string>|null $paths
     */
    public static function validateExistingFiles(?array $paths, string $fieldName, ?string $baseDir = null): void
    {
        if ($paths === null || count($paths) === 0) {
            return;
        }

        foreach ($paths as $index => $path) {
            self::validateExistingFile($path, "{$fieldName}[{$index}]", $baseDir);
        }
    }

    private static function validateOptionalToken(?string $value, string $fieldName): void
    {
        if ($value === null || trim($value) === '') {
            return;
        }

        if (preg_match(self::SAFE_TOKEN_PATTERN, $value) === 1) {
            return;
        }

        throw new InvalidArgumentException("{$fieldName} contains unsupported characters: {$value}");
    }

    private static function resolvePath(string $path, ?string $baseDir): string
    {
        if (self::isAbsolutePath($path) || $baseDir === null || $baseDir === '') {
            return $path;
        }

        return rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
    }

    private static function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        return match (true) {
            str_starts_with($path, '/'),
            str_starts_with($path, '\\\\'),
            preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 => true,
            default => false,
        };
    }
}
