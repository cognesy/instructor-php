<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Data;

final readonly class PlannedRegion
{
    public function __construct(
        public string $name,
        public string $path,
    ) {}
}