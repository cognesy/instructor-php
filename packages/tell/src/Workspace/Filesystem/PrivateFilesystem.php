<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Filesystem;

use Closure;
use Cognesy\Tell\Workspace\WorkspaceException;
use Throwable;

/** Private, symlink-safe filesystem operations shared by Tell workspace persistence. */
final readonly class PrivateFilesystem
{
    /**
     * @param  Closure(string, ?Throwable): Throwable  $integrityFailure
     * @param  Closure(string, ?Throwable): Throwable  $operationFailure
     * @param  Closure(string, ?Throwable): Throwable  $lockFailure
     */
    public function __construct(
        private Closure $integrityFailure,
        private Closure $operationFailure,
        private Closure $lockFailure,
    ) {}

    public static function forWorkspace(): self {
        $failure = static fn (string $message, ?Throwable $previous): Throwable => new WorkspaceException(
            $message,
            previous: $previous,
        );

        return new self($failure, $failure, $failure);
    }

    public function exists(string $path): bool {
        return file_exists($path) || is_link($path);
    }

    public function ensureDirectory(string $path, string $label): void {
        if (is_link($path)) {
            $this->integrity("Unsafe symlinked Tell {$label}: {$path}");
        }
        if (is_dir($path)) {
            return;
        }
        if ($this->exists($path)) {
            $this->integrity("Tell {$label} is not a directory: {$path}");
        }
        if (!@mkdir($path, 0700, true) && !is_dir($path)) {
            $this->operation("Unable to create Tell {$label}: {$path}");
        }
        @chmod($path, 0700);
    }

    public function assertDirectory(string $path, string $label): void {
        if (is_link($path)) {
            $this->integrity("Unsafe symlinked Tell {$label}: {$path}");
        }
        if (!is_dir($path)) {
            $this->integrity("Tell {$label} is not a directory: {$path}");
        }
    }

    public function assertFile(string $path, string $label): void {
        if (is_link($path)) {
            $this->integrity("Unsafe symlinked Tell {$label}: {$path}");
        }
        if (!is_file($path)) {
            $this->integrity("Tell {$label} is not a file: {$path}");
        }
    }

    public function read(string $path, string $label): string {
        $this->assertFile($path, $label);
        $contents = file_get_contents($path);
        if ($contents === false) {
            $this->integrity("Unable to read Tell {$label}: {$path}");
        }

        return $contents;
    }

    public function writeNew(string $path, string $bytes, string $label): void {
        if ($this->exists($path)) {
            $this->integrity("Tell {$label} already exists: {$path}");
        }
        $handle = $this->open($path, 'x', $label);
        try {
            $this->writeAll($handle, $bytes, $label);
        } finally {
            fclose($handle);
        }
    }

    public function writeAtomically(string $target, string $bytes, string $label, bool $replace): void {
        $directory = dirname($target);
        $this->ensureDirectory($directory, "{$label} directory");
        if (is_link($target)) {
            $this->integrity("Unsafe symlinked Tell {$label}: {$target}");
        }
        if (!$replace && $this->exists($target)) {
            $this->integrity("Tell {$label} already exists: {$target}");
        }

        $temporary = $directory . DIRECTORY_SEPARATOR . '.' . $this->temporaryLabel($label) . '.tmp.' . bin2hex(random_bytes(12));
        $handle = null;
        try {
            $handle = $this->open($temporary, 'x', "{$label} temporary file");
            $this->writeAll($handle, $bytes, $label);
            if (!fflush($handle)) {
                $this->operation("Unable to flush Tell {$label} temporary file.");
            }
            if (function_exists('fsync') && !fsync($handle)) {
                $this->operation("Unable to sync Tell {$label} temporary file.");
            }
            fclose($handle);
            $handle = null;

            if (!@rename($temporary, $target)) {
                $this->operation("Unable to atomically publish Tell {$label}.");
            }
            @chmod($target, 0600);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if ($this->exists($temporary) && !is_link($temporary)) {
                @unlink($temporary);
            }
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    public function withExclusiveLock(
        string $path,
        string $label,
        callable $operation,
        ?int $timeoutMilliseconds = null,
    ): mixed {
        $this->ensureDirectory(dirname($path), "{$label} directory");
        $handle = $this->open($path, 'c', $label);
        try {
            if ($timeoutMilliseconds === null) {
                if (!flock($handle, LOCK_EX)) {
                    $this->lock("Unable to acquire Tell {$label}.");
                }

                return $this->runLocked($handle, $operation);
            }
            $deadline = hrtime(true) + ($timeoutMilliseconds * 1_000_000);
            do {
                if (flock($handle, LOCK_EX | LOCK_NB)) {
                    return $this->runLocked($handle, $operation);
                }
                usleep(10_000);
            } while (hrtime(true) < $deadline);

            $this->lock("Timed out acquiring Tell {$label}.");
        } finally {
            fclose($handle);
        }
    }

    /**
     * @template T
     *
     * @param  resource  $handle
     * @param  callable(): T  $operation
     * @return T
     */
    private function runLocked($handle, callable $operation): mixed {
        try {
            return $operation();
        } finally {
            flock($handle, LOCK_UN);
        }
    }

    /** @return resource */
    private function open(string $path, string $mode, string $label) {
        if (is_link($path)) {
            $this->integrity("Unsafe symlinked Tell {$label}: {$path}");
        }
        $previousUmask = umask(0077);
        try {
            $handle = @fopen($path, $mode);
        } finally {
            umask($previousUmask);
        }
        if ($handle === false) {
            $this->operation("Unable to open Tell {$label}: {$path}");
        }
        @chmod($path, 0600);
        $pathStat = @lstat($path);
        $handleStat = fstat($handle);
        if ($pathStat === false || $handleStat === false || $pathStat['ino'] !== $handleStat['ino'] || $pathStat['dev'] !== $handleStat['dev']) {
            fclose($handle);
            $this->integrity("Tell {$label} changed while opening it: {$path}");
        }

        return $handle;
    }

    /** @param resource $handle */
    private function writeAll($handle, string $bytes, string $label): void {
        $offset = 0;
        $length = strlen($bytes);
        while ($offset < $length) {
            $written = fwrite($handle, substr($bytes, $offset));
            if ($written === false || $written === 0) {
                $this->operation("Unable to write Tell {$label}.");
            }
            $offset += $written;
        }
    }

    private function temporaryLabel(string $label): string {
        $normalized = preg_replace('/[^a-z0-9-]+/', '-', strtolower($label));

        return trim($normalized ?? 'file', '-') ?: 'file';
    }

    private function integrity(string $message, ?Throwable $previous = null): never {
        throw ($this->integrityFailure)($message, $previous);
    }

    private function operation(string $message, ?Throwable $previous = null): never {
        throw ($this->operationFailure)($message, $previous);
    }

    private function lock(string $message, ?Throwable $previous = null): never {
        throw ($this->lockFailure)($message, $previous);
    }
}
