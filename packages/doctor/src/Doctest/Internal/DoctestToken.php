<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Internal;

final readonly class DoctestToken
{
    public function __construct(
        public DoctestTokenType $type,
        public string $value,
        public int $line,
        public array $metadata = [],
    ) {}
}