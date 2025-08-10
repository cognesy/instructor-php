<?php declare(strict_types=1);

namespace Cognesy\Utils\Json\Partial;

final readonly class Token
{
    public function __construct(
        public TokenType $type,
        public mixed $value = null
    ) {}
}