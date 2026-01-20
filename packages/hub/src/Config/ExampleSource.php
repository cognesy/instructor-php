<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Config;

final readonly class ExampleSource
{
    public function __construct(
        public string $id,
        public string $baseDir,
    ) {}

    public static function fromPath(string $id, string $path): self
    {
        return new self($id, self::normalize($path));
    }

    private static function normalize(string $path): string
    {
        return rtrim($path, '/\\') . '/';
    }
}
