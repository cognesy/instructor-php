<?php
namespace Cognesy\Utils\Messages\Enums;

enum MessageRole : string {
    case System = 'system';
    case User = 'user';
    case Assistant = 'assistant';
    case Tool = 'tool';

    static public function fromString(string $role) : static
    {
        return match($role) {
            'system' => self::System,
            'user' => self::User,
            'assistant' => self::Assistant,
            'tool' => self::Tool,
            default => self::User,
        };
    }
}
