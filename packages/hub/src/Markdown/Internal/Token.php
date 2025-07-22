<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Markdown\Internal;

final readonly class Token
{
    public function __construct(
        public TokenType $type,
        public string $value,
        public int $line,
    ) {}
}