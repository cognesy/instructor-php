<?php

namespace Cognesy\Utils;

/**
 * Settings class provides utility methods for application configuration.
 */
class BasePath
{
    /**
     * Stores the base path once determined
     *
     * @var string|null
     */
    private static ?string $basePath = null;

    /**
     * Get the base path of the application
     *
     * @param string $path Optional path to append to the base path
     * @return string The absolute path to the application root
     * @throws \Exception When unable to determine the base path
     */
    public static function get(string $path = ''): string
    {
        // Return the cached path if it exists
        if (self::$basePath !== null) {
            return $path ? self::$basePath . DIRECTORY_SEPARATOR . ltrim($path, '/\\') : self::$basePath;
        }

        // Try each method in sequence to determine the base path
        self::$basePath = self::getBasePathFromEnv()
            ?? self::getBasePathFromComposer()
            ?? self::getBasePathFromReflection();

        // If we still don't have a base path, throw an exception
        if (self::$basePath === null) {
            throw new \Exception('Unable to determine application base path');
        }

        return $path ? self::$basePath . DIRECTORY_SEPARATOR . ltrim($path, '/\\') : self::$basePath;
    }

    /**
     * Manually set the application base path
     *
     * @param string $path The absolute path to set as the base path
     * @return void
     */
    public static function set(string $path): void
    {
        self::$basePath = rtrim($path, '/\\');
    }

    // INTERNAL //////////////////////////////////////////////////////////////////

    /**
     * Try to get the base path from the APP_BASE_PATH environment variable
     *
     * @return string|null The base path, or null if not found
     */
    private static function getBasePathFromEnv(): ?string
    {
        return isset($_ENV['APP_BASE_PATH']) ? rtrim($_ENV['APP_BASE_PATH'], '/\\') : null;
    }

    /**
     * Try to get the base path using Composer's ClassLoader
     *
     * @return string|null The base path, or null if not found
     */
    private static function getBasePathFromComposer(): ?string
    {
        try {
            if (class_exists(\Composer\Autoload\ClassLoader::class)) {
                $reflection = new \ReflectionClass(\Composer\Autoload\ClassLoader::class);
                $vendorDir = dirname($reflection->getFileName(), 2);
                return dirname($vendorDir);
            }
        } catch (\Throwable $e) {
            // Silently fail and try the next method
        }

        return null;
    }

    /**
     * Try to get the base path by walking up directories from current class file
     * until composer.json is found (Symfony-like approach)
     *
     * @return string|null The base path, or null if not found
     */
    private static function getBasePathFromReflection(): ?string
    {
        try {
            $reflection = new \ReflectionClass(self::class);
            $dir = dirname($reflection->getFileName());

            // Walk up directories until we find composer.json
            while (!file_exists($dir . '/composer.json')) {
                // If we've reached the filesystem root, abort
                if ($dir === dirname($dir)) {
                    return null;
                }
                $dir = dirname($dir);
            }

            return $dir;
        } catch (\Throwable $e) {
            // Silently fail
        }

        return null;
    }
}