<?php declare(strict_types=1);

namespace Cognesy\Instructor\Enums;

enum OutputMode : string
{
    case Tools = 'tool_call';
    case Json = 'json';
    case JsonSchema = 'json_schema';
    case MdJson = 'md_json';
    case Text = 'text';
    case Unrestricted = 'unrestricted';

    public function is(array|OutputMode $mode): bool
    {
        return match (true) {
            is_array($mode) => $this->isIn($mode),
            default => $this->value === $mode->value,
        };
    }

    public function isIn(array $modes): bool
    {
        return in_array($this, $modes, true);
    }

    public static function fromText(string $mode): OutputMode
    {
        return match ($mode) {
            'tool_call' => self::Tools,
            'json' => self::Json,
            'json_schema' => self::JsonSchema,
            'md_json' => self::MdJson,
            'text' => self::Text,
            default => self::Unrestricted,
        };
    }
}
