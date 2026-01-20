<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Config;

use Cognesy\InstructorHub\Data\ExampleLocation;

final readonly class ExampleMatchRule
{
    public function __construct(
        public string $pattern,
        public ?string $sourceId,
    ) {}

    public static function fromConfig(string $pattern, ?string $sourceId): self
    {
        return new self($pattern, $sourceId);
    }

    public function matches(ExampleLocation $location): bool
    {
        if ($this->sourceId !== null && $location->source->id !== $this->sourceId) {
            return false;
        }

        return $this->pathMatches($location->path);
    }

    private function pathMatches(string $path): bool
    {
        if ($this->pattern === '*') {
            return true;
        }

        if (str_contains($this->pattern, '*')) {
            return fnmatch($this->pattern, $path, FNM_PATHNAME);
        }

        if ($path === $this->pattern) {
            return true;
        }

        return str_starts_with($path, $this->pattern . '/');
    }
}
