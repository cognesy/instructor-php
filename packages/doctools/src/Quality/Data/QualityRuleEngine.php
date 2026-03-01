<?php declare(strict_types=1);

namespace Cognesy\Doctools\Quality\Data;

enum QualityRuleEngine : string
{
    case Regex = 'regex';
    case AstGrep = 'ast-grep';

    public static function fromString(string $value): self
    {
        return match (strtolower(trim($value))) {
            'regex' => self::Regex,
            'ast-grep', 'astgrep' => self::AstGrep,
            default => throw new \InvalidArgumentException("Unsupported rule engine: {$value}"),
        };
    }
}

