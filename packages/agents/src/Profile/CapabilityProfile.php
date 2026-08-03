<?php declare(strict_types=1);

namespace Cognesy\Agents\Profile;

final readonly class CapabilityProfile
{
    public function __construct(
        public string $name,
        public string $class,
    ) {}

    /** @return array{name: string, class: string} */
    public function toArray(): array {
        return ['name' => $this->name, 'class' => $this->class];
    }
}
