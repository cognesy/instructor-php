<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Data;

final readonly class PlannedCase
{
    /**
     * @param array<int,PlannedRegion> $regions
     */
    public function __construct(
        public string $id,
        public string $language,
        public string $path,
        public array $regions = [],
    ) {}
}

