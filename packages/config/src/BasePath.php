<?php declare(strict_types=1);

namespace Cognesy\Config;

use Composer\Autoload\ClassLoader;
use Generator;
use ReflectionClass;
use Throwable;

/**
 * BasePath class provides a method to determine the base path of the application
 */
class BasePath
{
    /**
     * Singleton instance
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Stores the base path once determined
     *
     * @var string|null
     */
    private ?string $basePath = null;
    private $detectionMethods = [
        'getBasePathFromEnv',
        'getBasePathFromCwd',
        'getBasePathFromComposer',
        'getBasePathFromServerVars',
        'getBasePathFromReflection',
        'getBasePathFromFrameworkPatterns',
    ];

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {}

    /**
     * Get the singleton instance
     *
     * @return self
     */
    private static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get the base path of the application
     *
     * @param string $path Optional path to append to the base path
     * @return string The absolute path to the application root
     * @throws \Exception When unable to determine the base path
     */
    public static function get(string $path = ''): string {
        return self::getInstance()->getPath($path);
    }

    /**
     * Manually set the application base path
     *
     * @param string $path The absolute path to set as the base path
     * @return void
     */
    public static function set(string $path): void {
        self::getInstance()->setPath($path);
    }

    /**
     * Set order and available detection methods
     * @param array $methods
     * @return void
     */
    public static function withDetectionMethods(array $methods): void {
        $instance = self::getInstance();
        $instance->detectionMethods = $methods;
        // Reset base path to force re-detection
        $instance->basePath = null;
    }

    // INTERNAL //////////////////////////////////////////////////////////////////

    private function detectionMethods(): Generator {
        yield from $this->detectionMethods;
    }

    /**
     * Internal method to get the path
     *
     * @param string $path Optional path to append to the base path
     * @return string The absolute path to the application root
     * @throws \Exception When unable to determine the base path
     */
    private function getPath(string $path = ''): string {
        // if provided path has a leading slash, return it as is
        if ($this->startsWithSlash($path)) {
            return $path;
        }

        // Return the cached path if it exists
        if ($this->basePath !== null) {
            return $this->makePath($path);
        }

        // Try each method in sequence to determine the base path
        foreach ($this->detectionMethods() as $method) {
            $result = $this->$method();
            if ($result && $this->validateBasePath($result)) {
                $this->basePath = $result;
                return $this->makePath($path);
            }
        }

        // If we still don't have a base path, throw an exception
        throw new \Exception('Unable to determine application base path');
    }

    /**
     * Internal method to set the path
     *
     * @param string $path The absolute path to set as the base path
     * @return void
     */
    private function setPath(string $path): void {
        $this->basePath = rtrim($path, '/\\');
    }

    /**
     * Create a full path by appending the given path to the base path
     *
     * @param string|null $path The path to append
     * @return string The full path
     */
    private function makePath(?string $path = null): string {
        return $path
            ? $this->basePath . DIRECTORY_SEPARATOR . ltrim($path, '/\\')
            : $this->basePath;
    }

    /**
     * Check if the given path starts with a slash
     *
     * @param string $path The path to check
     * @return bool True if the path starts with a slash, false otherwise
     */
    private function startsWithSlash(string $path): bool {
        return str_starts_with($path, '/') || str_starts_with($path, '\\');
    }

    /**
     * Validate that the given path is a valid base path
     *
     * @param string $path The path to validate
     * @return bool True if valid, false otherwise
     */
    private function validateBasePath(string $path): bool {
        if (empty($path)) {
            return false;
        }

        return is_dir($path) &&
            is_readable($path) &&
            file_exists($path . '/composer.json');
    }

    /**
     * Try to get the base path from environment variables
     *
     * @return string|null The base path, or null if not found
     */
    private function getBasePathFromEnv(): ?string {
        $envVars = ['APP_BASE_PATH', 'APP_ROOT', 'PROJECT_ROOT', 'BASE_PATH'];

        foreach ($envVars as $var) {
            $raw = $_ENV[$var] ?? $_SERVER[$var] ?? getenv($var);
            if (!is_string($raw) || $raw === '') {
                continue;
            }
            $path = $raw;
            if (is_dir($path)) {
                return rtrim($path, '/\\');
            }
        }
        return null;
    }

    /**
     * Try to get the base path from current working directory
     *
     * @return string|null The base path, or null if not found
     */
    private function getBasePathFromCwd(): ?string {
        $cwd = getcwd();
        if ($cwd && file_exists($cwd . '/composer.json')) {
            return $cwd;
        }
        return null;
    }

    /**
     * Try to get the base path using Composer's ClassLoader
     *
     * @return string|null The base path, or null if not found
     */
    private function getBasePathFromComposer(): ?string {
        try {
            // Method 1: Use ClassLoader reflection
            if (class_exists(ClassLoader::class)) {
                $reflection = new ReflectionClass(ClassLoader::class);
                $vendorDir = dirname($reflection->getFileName(), 2);
                $baseDir = dirname($vendorDir);

                if (file_exists($baseDir . '/composer.json')) {
                    return $baseDir;
                }
            }

            // Method 2: Look for autoloader files
            $autoloadFiles = [
                __DIR__ . '/../../autoload.php',
                __DIR__ . '/../vendor/autoload.php',
                __DIR__ . '/vendor/autoload.php',
            ];

            foreach ($autoloadFiles as $file) {
                if (file_exists($file)) {
                    $vendorDir = dirname($file);
                    $baseDir = dirname($vendorDir);
                    if (file_exists($baseDir . '/composer.json')) {
                        return $baseDir;
                    }
                }
            }
        } catch (Throwable $e) {
            // Silently fail and try the next method
        }
        return null;
    }

    /**
     * Try to get the base path from server variables
     *
     * @return string|null The base path, or null if not found
     */
    private function getBasePathFromServerVars(): ?string {
        $candidates = [
            $_SERVER['DOCUMENT_ROOT'] ?? null,
            dirname($_SERVER['SCRIPT_FILENAME'] ?? ''),
            $_SERVER['PWD'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate && file_exists($candidate . '/composer.json')) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * Try to get the base path using common framework patterns
     *
     * @return string|null The base path, or null if not found
     */
    private function getBasePathFromFrameworkPatterns(): ?string {
        $patterns = [
            'public/index.php',
            'web/index.php',
            'webroot/index.php',
            'htdocs/index.php',
        ];

        try {
            $reflection = new ReflectionClass(self::class);
            $dir = dirname($reflection->getFileName());

            while ($dir !== dirname($dir)) {
                foreach ($patterns as $pattern) {
                    if (file_exists($dir . '/' . $pattern)) {
                        return $dir;
                    }
                }
                $dir = dirname($dir);
            }
        } catch (Throwable $e) {
            // Silently fail
        }
        return null;
    }

    /**
     * Try to get the base path by walking up directories from current class file
     * until composer.json is found (Symfony-like approach)
     *
     * @return string|null The base path, or null if not found
     */
    private function getBasePathFromReflection(): ?string {
        try {
            $reflection = new ReflectionClass(self::class);
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
        } catch (Throwable $e) {
            // Silently fail
        }
        return null;
    }
}
