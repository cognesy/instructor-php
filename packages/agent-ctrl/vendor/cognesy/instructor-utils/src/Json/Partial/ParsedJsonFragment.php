<?php declare(strict_types=1);

namespace Cognesy\Utils\Json\Partial;

final readonly class ParsedJsonFragment
{
    public function __construct(
        public mixed $value,
        public int $startIndex,
        public int $endIndex, // exclusive; where tokenizer stopped
    ) {}
}