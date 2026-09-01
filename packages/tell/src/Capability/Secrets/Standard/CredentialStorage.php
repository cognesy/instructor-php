<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Secrets\Standard;

use Cognesy\Tell\Core\Paths\TellPaths;

use RuntimeException;

/** @internal */
final readonly class CredentialStorage
{
    public function __construct(private TellPaths $paths) {}

    public function ensureConfig(): string {
        $this->ensurePrivateDirectories(
            $this->paths->home,
            $this->paths->configDirectory,
        );

        return $this->paths->configDirectory;
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
