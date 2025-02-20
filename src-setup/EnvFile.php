<?php

namespace Cognesy\Setup;

use Cognesy\Utils\Settings;
use ReflectionClass;
use RuntimeException;

/**
 * Manages environment file operations including parsing, merging, and writing.
 */
class EnvFile
{
    private const HEADER_NEW_VARS = '# INSTRUCTOR PHP // added new variables';
    private const CONFIG_PATH_KEY = 'INSTRUCTOR_CONFIG_PATH';

    public const RESULT_OK = 0;
    public const RESULT_ERROR = 1;
    public const RESULT_NOOP = 2;

    public function __construct(
        private readonly bool       $noOp,
        private readonly Output     $output,
        private readonly Filesystem $filesystem,
    ) {}

    /**
     * Merges environment variables from the source file into the destination file.
     *
     * @throws RuntimeException If paths cannot be resolved or files cannot be accessed.
     */
    public function mergeEnvFiles(string $source, string $dest, string $configDir): int
    {
        if ($this->noOp) {
            return $this->processNoOpMerge($configDir);
        }

        try {
            $mergedContent = $this->prepareMergedContent($source, $dest, $configDir);

            if ($mergedContent === null) {
                $this->output->out("No new variables to merge");
                return self::RESULT_OK;
            }

            $this->filesystem->writeFile($dest, $mergedContent);
            $this->output->out("Merged env files into:\n $dest");
            return self::RESULT_OK;

        } catch (\Exception $e) {
            $this->output->out("<red>Failed to merge env files:</red> " . $e->getMessage(), 'error');
            return self::RESULT_ERROR;
        }
    }

    /**
     * Prepares merged content for the destination file.
     */
    private function prepareMergedContent(string $source, string $dest, string $configDir): ?string
    {
        $sourceVars = $this->parseEnvFile($source);
        $destContent = $this->filesystem->exists($dest) ? $this->filesystem->readFile($dest) : '';
        $destVars = $this->parseEnvFile($dest);

        $newVars = $this->getNewVariables($sourceVars, $destVars, $configDir);

        if (empty($newVars)) {
            return null;
        }

        return $this->updateEnvContent($destContent, $newVars);
    }

    /**
     * Updates the environment file content by replacing or adding new variables.
     */
    private function updateEnvContent(string $destContent, array $newVars): string
    {
        $lines = explode("\n", $destContent);
        $updated = false;

        foreach ($lines as &$line) {
            foreach ($newVars as $key => $value) {
                if (str_starts_with(trim($line), "$key=")) {
                    $line = "$key=$value";
                    unset($newVars[$key]);
                    $updated = true;
                }
            }
            if (empty($newVars)) {
                break;
            }
        }
        unset($line); // Break the reference with the last element

        // If there are still new variables to add, append them under the new vars header
        if (!empty($newVars)) {
            $lines[] = '';
            $lines[] = self::HEADER_NEW_VARS;
            foreach ($newVars as $key => $value) {
                $lines[] = "$key=$value";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Parses an environment file into key-value pairs.
     */
    private function parseEnvFile(string $file): array
    {
        if (!$this->filesystem->exists($file)) {
            return [];
        }

        return array_reduce(explode("\n", $this->filesystem->readFile($file)), function ($vars, $line) {
            $line = trim($line);
            if ($this->shouldSkipLine($line)) {
                return $vars;
            }

            [$key, $value] = $this->parseEnvLine($line);
            $vars[$key] = $value;
            return $vars;
        }, []);
    }

    /**
     * Determines if a line should be skipped during parsing.
     */
    private function shouldSkipLine(string $line): bool
    {
        return $line === '' || str_starts_with($line, '#') || !str_contains($line, '=');
    }

    /**
     * Parses a single environment file line into a key-value pair.
     */
    private function parseEnvLine(string $line): array
    {
        [$key, $value] = explode('=', $line, 2);
        return [trim($key), trim($value)];
    }

    /**
     * Determines which variables need to be added or updated in the destination file.
     *
     * @throws RuntimeException If config directory cannot be resolved.
     */
    private function getNewVariables(array $sourceVars, array $destVars, string $configDir): array
    {
        // Identify new variables present in the source but not in the destination
        $newVars = array_diff_key($sourceVars, $destVars);

        // Prepare the CONFIG_PATH_KEY variable
        $configDirAbs = $this->resolveConfigDir($configDir);
        $settingsDir = $this->getSettingsBaseDir();
        $relativeConfigPath = $this->getRelativePath($settingsDir, $configDirAbs);

        $newVars[self::CONFIG_PATH_KEY] = $relativeConfigPath;

        return $newVars;
    }

    /**
     * Gets the base directory containing Settings.php.
     */
    private function getSettingsBaseDir(): string
    {
        return dirname((new ReflectionClass(Settings::class))->getFileName());
    }

    /**
     * Processes merge operation in no-op mode.
     *
     * @throws RuntimeException If config directory cannot be resolved.
     */
    private function processNoOpMerge(string $configDir): int
    {
        try {
            $configDirAbs = $this->resolveConfigDir($configDir);
            $settingsDir = $this->getSettingsBaseDir();
            $relativeConfigPath = $this->getRelativePath($settingsDir, $configDirAbs);

            $this->output->out("<yellow>Would set " . self::CONFIG_PATH_KEY . " to:</yellow>\n $relativeConfigPath");
            return self::RESULT_NOOP;
        } catch (\Exception $e) {
            $this->output->out("<red>Failed to process no-op merge:</red> " . $e->getMessage(), 'error');
            return self::RESULT_ERROR;
        }
    }

    /**
     * Resolves and validates a config directory path.
     *
     * @throws RuntimeException If path cannot be resolved and not in no-op mode.
     */
    private function resolveConfigDir(string $configDir): string
    {
        $configDirAbs = realpath($configDir);
        if (!$configDirAbs) {
            if ($this->noOp) {
                // In no-op mode, allow unresolved paths by treating them as relative to the current directory
                return rtrim($configDir, DIRECTORY_SEPARATOR);
            }
            throw new RuntimeException("Cannot resolve config directory: $configDir");
        }
        return $configDirAbs;
    }

    /**
     * Calculates the relative path between two filesystem locations.
     *
     * @throws RuntimeException If paths cannot be resolved or are invalid.
     */
    private function getRelativePath(string $fromPath, string $toPath): string
    {
        // In no-op mode, toPath might not be resolvable. Handle accordingly.
        if ($this->noOp && !is_dir($toPath)) {
            // Treat toPath as relative to fromPath
            return $this->computeRelativePath($fromPath, $toPath);
        }

        $fromPath = realpath($fromPath);
        $toPath = realpath($toPath);

        if (!$fromPath || !$toPath) {
            throw new RuntimeException("Failed to resolve paths for relative path calculation.");
        }

        $fromParts = explode(DIRECTORY_SEPARATOR, $fromPath);
        $toParts = explode(DIRECTORY_SEPARATOR, $toPath);

        // Find common path
        $commonLength = 0;
        $maxCommon = min(count($fromParts), count($toParts));
        while ($commonLength < $maxCommon && $fromParts[$commonLength] === $toParts[$commonLength]) {
            $commonLength++;
        }

        // Calculate how many directories to go up from 'fromPath'
        $backtracks = array_fill(0, count($fromParts) - $commonLength, '..');
        // Append the remaining part of 'toPath'
        $remaining = array_slice($toParts, $commonLength);

        return implode('/', array_merge($backtracks, $remaining));
    }

    /**
     * Computes relative path without relying on realpath.
     * This is used in no-op mode when toPath may not exist.
     */
    private function computeRelativePath(string $fromPath, string $toPath): string
    {
        $fromPath = rtrim($fromPath, DIRECTORY_SEPARATOR);
        $toPath = rtrim($toPath, DIRECTORY_SEPARATOR);

        $fromParts = explode(DIRECTORY_SEPARATOR, $fromPath);
        $toParts = explode(DIRECTORY_SEPARATOR, $toPath);

        // Find common path
        $commonLength = 0;
        $maxCommon = min(count($fromParts), count($toParts));
        while ($commonLength < $maxCommon && $fromParts[$commonLength] === $toParts[$commonLength]) {
            $commonLength++;
        }

        // Calculate how many directories to go up from 'fromPath'
        $backtracks = array_fill(0, count($fromParts) - $commonLength, '..');
        // Append the remaining part of 'toPath'
        $remaining = array_slice($toParts, $commonLength);

        return implode('/', array_merge($backtracks, $remaining));
    }
}
