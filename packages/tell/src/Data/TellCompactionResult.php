<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

/** A storage-independent report of an explicitly compacted Tell conversation. */
final readonly class TellCompactionResult
{
    /** @param array{name: string, type: 'main'|'branch'|'session'|'ref', source?: string} $selector
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public array $selector,
        public array $details,
    ) {}
}
