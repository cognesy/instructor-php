<?php declare(strict_types=1);

namespace Cognesy\Agents\Profile;

final readonly class AgentIdentity
{
    public function __construct(
        public string $name,
        public string $description,
    ) {}

    public static function anonymous(): self {
        return new self(name: 'anonymous', description: '');
    }

    /** @return array{name: string, description: string} */
    public function toArray(): array {
        return [
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}
