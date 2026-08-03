<?php declare(strict_types=1);

namespace Cognesy\Agents\Profile;

final readonly class HookProfile
{
    /** @param list<string> $triggers */
    public function __construct(
        public string $class,
        public array $triggers,
        public int $priority,
        public ?string $name,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'class' => $this->class,
            'triggers' => $this->triggers,
            'priority' => $this->priority,
            'name' => $this->name,
        ];
    }
}
