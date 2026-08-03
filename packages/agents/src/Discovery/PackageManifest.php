<?php declare(strict_types=1);

namespace Cognesy\Agents\Discovery;

final readonly class PackageManifest
{
    /**
     * @param array<string, class-string> $capabilities
     * @param array<string, class-string> $tools
     */
    public function __construct(
        public string $packageName,
        public array $capabilities,
        public array $tools,
        public bool $root = false,
    ) {}
}
