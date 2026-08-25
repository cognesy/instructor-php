<?php

declare(strict_types=1);

namespace Cognesy\Tell;

/** The read-only context estimate prepared for a Tell conversation. */
final readonly class TellContext
{
    /** @param array<string, mixed> $details */
    public function __construct(public array $details) {}
}
