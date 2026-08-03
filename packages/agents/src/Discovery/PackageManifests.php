<?php declare(strict_types=1);

namespace Cognesy\Agents\Discovery;

final readonly class PackageManifests
{
    /** @var list<PackageManifest> */
    private array $manifests;

    public function __construct(PackageManifest ...$manifests) {
        $this->manifests = $manifests;
    }

    public static function empty(): self {
        return new self();
    }

    public function with(PackageManifest $manifest): self {
        return new self(...[...$this->manifests, $manifest]);
    }

    /** @return list<PackageManifest> */
    public function all(): array {
        return $this->manifests;
    }

    public function count(): int {
        return count($this->manifests);
    }
}
