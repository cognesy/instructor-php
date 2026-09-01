<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

/** Read-only public projection of one durable workspace session. */
final readonly class TellSessionView
{
    /** @param array<string, mixed> $details */
    public function __construct(public array $details) {}
}
