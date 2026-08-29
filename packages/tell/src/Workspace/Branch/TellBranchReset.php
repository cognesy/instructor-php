<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Branch;

/** Result of moving one branch ref to a verified earlier immutable-history head. */
final readonly class TellBranchReset
{
    public function __construct(
        public string $branch,
        public ?string $previousHead,
        public ?string $head,
        public int $distance,
        public bool $changed,
    ) {}
}
