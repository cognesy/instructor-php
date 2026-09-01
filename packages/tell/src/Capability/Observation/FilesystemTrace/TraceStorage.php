<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Observation\FilesystemTrace;

use Cognesy\Tell\Core\Paths\TellPaths;
use RuntimeException;

/** @internal */
final readonly class TraceStorage
{
    public function __construct(private TellPaths $paths) {}

    public function ensureTraceDate(string $date): string {
        $directory = $this->paths->executionTraces . DIRECTORY_SEPARATOR . $date;
        $this->ensurePrivateDirectories(
            $this->paths->home,
            $this->paths->logs,
            $this->paths->executionTraces,
            $directory,
        );

        return $directory;
    }

    public function ensureSessionTraces(): string {
        $this->ensurePrivateDirectories(
            $this->paths->home,
            $this->paths->logs,
            $this->paths->sessionTraces,
        );

        return $this->paths->sessionTraces;
    }

    private function ensurePrivateDirectories(string ...$directories): void {
        foreach ($directories as $directory) {
            if (file_exists($directory) && !is_dir($directory)) {
                throw new RuntimeException("Tell storage path is not a directory: {$directory}");
            }
            if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new RuntimeException("Failed to create Tell storage directory: {$directory}");
            }
            @chmod($directory, 0700);
        }
    }
}
