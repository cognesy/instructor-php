<?php declare(strict_types=1);

namespace Cognesy\Doctools\Quality\Data;

enum QualityRuleScope : string
{
    case Markdown = 'markdown';
    case PhpSnippet = 'php-snippet';

    public static function fromString(string $value): self
    {
        return match (strtolower(trim($value))) {
            'markdown', 'document' => self::Markdown,
            'php-snippet', 'snippet' => self::PhpSnippet,
            default => throw new \InvalidArgumentException("Unsupported rule scope: {$value}"),
        };
    }
}

