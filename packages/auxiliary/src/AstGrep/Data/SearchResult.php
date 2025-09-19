<?php
declare(strict_types=1);

namespace Cognesy\Auxiliary\AstGrep\Data;

readonly class SearchResult
{
    public function __construct(
        public string $file,
        public int $line,
        public string $match,
        public array $context = [],
    ) {}

    public function getRelativePath(string $basePath): string {
        $basePath = rtrim($basePath, '/') . '/';
        if (str_starts_with($this->file, $basePath)) {
            return substr($this->file, strlen($basePath));
        }
        return $this->file;
    }

    public function getDirectory(): string {
        return dirname($this->file);
    }

    public function getFilename(): string {
        return basename($this->file);
    }

    public function getMatchPreview(int $maxLength = 80): string {
        $preview = str_replace(["\n", "\r", "\t"], [' ', '', ' '], $this->match);
        $preview = preg_replace('/\s+/', ' ', $preview);

        if (strlen($preview) > $maxLength) {
            return substr($preview, 0, $maxLength - 3) . '...';
        }

        return $preview;
    }

    public function toArray(): array {
        return [
            'file' => $this->file,
            'line' => $this->line,
            'match' => $this->match,
            'context' => $this->context,
        ];
    }
}