<?php declare(strict_types=1);

namespace Cognesy\Doctools\Quality\Data;

final class DriftReport
{
    /**
     * @param list<PackageDrift> $packages
     */
    public function __construct(
        public readonly array $packages,
    ) {}

    public function highDrift(): array
    {
        return array_filter($this->packages, fn(PackageDrift $p) => $p->risk === 'high');
    }

    public function mediumDrift(): array
    {
        return array_filter($this->packages, fn(PackageDrift $p) => $p->risk === 'medium');
    }

    public function lowDrift(): array
    {
        return array_filter($this->packages, fn(PackageDrift $p) => $p->risk === 'low');
    }

    public function toArray(): array
    {
        return array_map(fn(PackageDrift $p) => $p->toArray(), $this->packages);
    }
}
