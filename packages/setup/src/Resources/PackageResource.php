<?php declare(strict_types=1);

namespace Cognesy\Setup\Resources;

final readonly class PackageResource
{
    public function __construct(
        public string $package,
        public string $sourcePath,
        public string $destinationPath,
    ) {}
}

