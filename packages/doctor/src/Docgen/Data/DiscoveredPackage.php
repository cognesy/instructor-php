<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen\Data;

readonly class DiscoveredPackage
{
    public function __construct(
        public string $name,
        public string $docsPath,
        public string $description,
        public string $targetDir,
    ) {}

    /**
     * Get formatted title for navigation
     */
    public function getTitle(): string
    {
        return match ($this->name) {
            'http-client' => 'HTTP Client',
            default => ucfirst($this->name),
        };
    }
}
