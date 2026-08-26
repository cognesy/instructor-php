<?php

declare(strict_types=1);

namespace Cognesy\InstructorHub\Services;

/**
 * A PHP script materialized from an example source document.
 *
 * Cookbook examples are Markdown documents that contain one executable PHP
 * fence. The temporary script is created beside the source document so code
 * that uses __DIR__ keeps the same relative-path semantics as the example.
 */
final class ExampleScript
{
    private function __construct(
        public readonly string $path,
        private readonly ?string $temporaryPath = null,
    ) {}

    public static function fromRunPath(string $runPath): self
    {
        $content = file_get_contents($runPath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read example: {$runPath}");
        }

        if (! str_starts_with($content, '---')) {
            return new self($runPath);
        }

        if (preg_match('/^```php[^\r\n]*\R(.*?)^```[ \t]*$/ms', $content, $matches) !== 1) {
            throw new \RuntimeException("Markdown example has no executable PHP fence: {$runPath}");
        }

        $temporaryPath = tempnam(dirname($runPath), '.hub-example-');
        if ($temporaryPath === false) {
            throw new \RuntimeException("Failed to create temporary script for: {$runPath}");
        }

        if (file_put_contents($temporaryPath, $matches[1]) === false) {
            @unlink($temporaryPath);
            throw new \RuntimeException("Failed to materialize PHP fence for: {$runPath}");
        }

        return new self($temporaryPath, $temporaryPath);
    }

    public function cleanup(): void
    {
        if ($this->temporaryPath !== null && file_exists($this->temporaryPath)) {
            @unlink($this->temporaryPath);
        }
    }
}
