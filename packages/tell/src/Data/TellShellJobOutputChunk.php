<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

final readonly class TellShellJobOutputChunk
{
    public function __construct(
        public int $cursor,
        public string $stream,
        public string $text,
    ) {}
}
