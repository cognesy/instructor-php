<?php declare(strict_types=1);

namespace Cognesy\Config;

use InvalidArgumentException;

final readonly class ConfigFileSet
{
    /** @var array<int, string> */
    private array $files;
    /** @var array<string, string> */
    private array $keysByFile;

    /**
     * @param array<int, string> $files
     * @param array<string, string> $keysByFile
     */
    public function __construct(array $files, array $keysByFile)
    {
        $this->files = $files;
        $this->keysByFile = $keysByFile;
    }

    public static function fromFiles(string ...$files): self
    {
        $resolvedFiles = [];
        $keysByFile = [];
        foreach ($files as $file) {
            $path = self::resolveFilePath($file);
            $resolvedFiles[$path] = $path;
            $keysByFile[$path] = ConfigKey::fromPath($path);
        }

        if ($resolvedFiles === []) {
            throw new InvalidArgumentException('ConfigFileSet requires at least one existing config file');
        }

        $orderedFiles = array_values($resolvedFiles);
        usort(
            $orderedFiles,
            fn(string $left, string $right): int => strcmp($keysByFile[$left], $keysByFile[$right]),
        );

        return new self($orderedFiles, $keysByFile);
    }

    /**
     * @param array<string, string> $filesByKey
     */
    public static function fromKeyedFiles(array $filesByKey): self
    {
        if ($filesByKey === []) {
            throw new InvalidArgumentException('ConfigFileSet requires at least one existing config file');
        }

        ksort($filesByKey);

        $files = [];
        $keysByFile = [];
        foreach ($filesByKey as $key => $file) {
            $path = self::resolveFilePath($file);
            $files[] = $path;
            $keysByFile[$path] = $key;
        }

        return new self($files, $keysByFile);
    }

    /** @return array<int, string> */
    public function all(): array
    {
        return $this->files;
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_map(
            fn(string $file): string => $this->keysByFile[$file],
            $this->files,
        );
    }

    public function keyFor(string $file): string
    {
        $path = self::resolveFilePath($file);

        return $this->keysByFile[$path]
            ?? throw new InvalidArgumentException("Config file is not part of the file set: {$file}");
    }

    public function count(): int
    {
        return count($this->files);
    }

    public function isEmpty(): bool
    {
        return $this->files === [];
    }

    public function filesHash(): string
    {
        $descriptors = [];
        foreach ($this->files as $file) {
            $hash = hash_file('sha256', $file);
            if ($hash === false) {
                throw new InvalidArgumentException("Cannot hash config file: {$file}");
            }
            $descriptors[] = [
                'key' => $this->keysByFile[$file],
                'hash' => $hash,
            ];
        }

        return hash('sha256', json_encode($descriptors, JSON_THROW_ON_ERROR));
    }

    private static function resolveFilePath(string $file): string
    {
        $trimmed = trim($file);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Config file path cannot be empty');
        }

        $resolved = realpath($trimmed);
        $path = match (true) {
            is_string($resolved) && $resolved !== '' => $resolved,
            default => $trimmed,
        };

        if (!is_file($path)) {
            throw new InvalidArgumentException("Config file does not exist: {$file}");
        }

        return $path;
    }
}
