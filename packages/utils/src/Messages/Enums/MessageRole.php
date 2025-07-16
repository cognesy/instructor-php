<?php declare(strict_types=1);

namespace Cognesy\Utils\Messages\Enums;

enum MessageRole : string {
    case System = 'system';
    case Developer = 'developer';
    case User = 'user';
    case Assistant = 'assistant';
    case Tool = 'tool';

    static public function fromString(string $role) : static
    {
        return match($role) {
            'system' => self::System,
            'developer' => self::Developer,
            'user' => self::User,
            'assistant' => self::Assistant,
            'tool' => self::Tool,
            default => self::User,
        };
    }

    public function is(MessageRole $role) : bool {
        return $this === $role;
    }

    public function isNot(MessageRole $role) : bool {
        return !$this->is($role);
    }

    public function oneOf(MessageRole ...$roles) : bool {
        foreach ($roles as $role) {
            if ($this->is($role)) {
                return true;
            }
        }
        return false;
    }

    public function isSystem() : bool {
        return $this->oneOf(self::System, self::Developer);
    }

    public static function normalizeArray(array $roles) : array {
        return array_map(fn($role) => match(true) {
            is_string($role) => self::fromString($role),
            $role instanceof MessageRole => $role,
            default => throw new \InvalidArgumentException("Invalid role type: " . gettype($role)),
        }, $roles);
    }
}
