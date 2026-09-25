<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Data;

final readonly class Projection
{
    public function __construct(
        public bool $metadata = true,
        public bool $content = true,
        public bool $vector = false,
    ) {}

    public static function default(): self
    {
        return new self;
    }
}
