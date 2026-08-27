<?php

declare(strict_types=1);

namespace Cognesy\Tell\Branch;

/** Safe public view of one immutable-history Tell branch. */
final readonly class TellBranchInfo
{
    /** @param array<string, mixed>|null $created */
    public function __construct(
        public string $name,
        public ?string $head,
        public bool $empty,
        public int $turnCount,
        public bool $current,
        public ?array $created = null,
    ) {}
}
