<?php declare(strict_types=1);

namespace Cognesy\Doctools\Docgen\Data;

readonly class DiscoveredCheatsheet
{
    public function __construct(
        public string $package,
        public string $title,
        public string $description,
        public string $sourcePath,
    ) {}

    /**
     * Get the target filename (without extension)
     */
    public function getTargetName(): string
    {
        return $this->package;
    }
}
