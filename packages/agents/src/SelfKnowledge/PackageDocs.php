<?php declare(strict_types=1);

namespace Cognesy\Agents\SelfKnowledge;

final readonly class PackageDocs
{
    private function __construct(
        private string $packageRoot,
        private ?string $examplesRoot = null,
    ) {}

    public static function installed(?string $examplesRoot = null): self {
        return new self(
            packageRoot: dirname(__DIR__, 2),
            examplesRoot: $examplesRoot,
        );
    }

    public static function fromPackageRoot(string $packageRoot, ?string $examplesRoot = null): self {
        return new self(
            packageRoot: rtrim($packageRoot, DIRECTORY_SEPARATOR),
            examplesRoot: $examplesRoot,
        );
    }

    public function docsPath(): string {
        return $this->resolve($this->packageRoot . '/resources/docs');
    }

    public function readmePath(): string {
        return $this->resolve($this->packageRoot . '/README.md');
    }

    public function examplesPath(): ?string {
        if ($this->examplesRoot === null) {
            return null;
        }
        $path = realpath($this->examplesRoot);
        return is_string($path) && is_dir($path) ? $path : null;
    }

    public function exists(): bool {
        return is_dir($this->docsPath()) && is_file($this->readmePath());
    }

    private function resolve(string $path): string {
        $resolved = realpath($path);
        return is_string($resolved) ? $resolved : $path;
    }
}
