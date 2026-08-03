<?php declare(strict_types=1);

namespace Cognesy\Agents\Profile;

final readonly class CapabilityProfileList
{
    /** @var list<CapabilityProfile> */
    private array $profiles;

    public function __construct(CapabilityProfile ...$profiles) {
        $this->profiles = $profiles;
    }

    public static function empty(): self {
        return new self();
    }

    public function with(CapabilityProfile $profile): self {
        return new self(...[...$this->profiles, $profile]);
    }

    /** @return list<CapabilityProfile> */
    public function all(): array {
        return $this->profiles;
    }

    /** @return list<array{name: string, class: string}> */
    public function toArray(): array {
        return array_map(
            static fn (CapabilityProfile $profile): array => $profile->toArray(),
            $this->profiles,
        );
    }
}
