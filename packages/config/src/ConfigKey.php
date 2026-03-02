<?php declare(strict_types=1);

namespace Cognesy\Config;

use InvalidArgumentException;

final class ConfigKey
{
    public static function fromPath(string $path): string {
        $normalizedPath = str_replace('\\', '/', $path);
        $relativePath = self::relativePath($normalizedPath);
        $withoutExtension = preg_replace('~\.(?:yaml|yml|php)$~i', '', $relativePath) ?? $relativePath;
        $key = trim(str_replace('/', '.', $withoutExtension), '.');
        if ($key === '') {
            throw new InvalidArgumentException("Cannot derive config key from path: {$path}");
        }
        return $key;
    }

    private static function relativePath(string $path): string {
        $matches = [];
        $hasConfigSegment = preg_match('~(?:^|/)config/(.+)$~', $path, $matches) === 1;
        return match (true) {
            $hasConfigSegment => (string) $matches[1],
            default => basename($path),
        };
    }
}
