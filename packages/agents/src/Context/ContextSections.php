<?php declare(strict_types=1);

namespace Cognesy\Agents\Context;

final class ContextSections
{
    public const string DEFAULT = 'messages';
    public const string BUFFER = 'buffer';
    public const string SUMMARY = 'summary';

    /**
     * @return string[]
     */
    public static function inferenceOrder(): array {
        return [
            self::SUMMARY,
            self::BUFFER,
            self::DEFAULT,
        ];
    }
}
