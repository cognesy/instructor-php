<?php declare(strict_types=1);

namespace Cognesy\Agents\Template;

use InvalidArgumentException;

final readonly class AgentDefinitionName
{
    private const PATTERN = '/^[a-z0-9][a-z0-9_-]*$/';

    private function __construct(private string $value) {}

    public static function fromString(string $name): self {
        if (!self::isValid($name)) {
            throw new InvalidArgumentException(
                "Invalid agent name '{$name}'; expected " . self::PATTERN . '.',
            );
        }
        return new self($name);
    }

    public static function isValid(string $name): bool {
        return preg_match(self::PATTERN, $name) === 1;
    }

    public function value(): string {
        return $this->value;
    }

    public function filename(string $extension = 'md'): string {
        return $this->value . '.' . ltrim($extension, '.');
    }
}
