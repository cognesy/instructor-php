<?php declare(strict_types=1);

namespace Cognesy\Setup\Config;

final readonly class ConfigPublishEntry
{
    public function __construct(
        public string $package,
        public string $namespace,
        public string $sourcePath,
        public string $relativePath,
        public string $destinationPath,
    ) {}
}
