<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

/** A bounded, read-only projection of a Tell conversation. */
final readonly class TellConversationView
{
    /** @param array{name: string, type: 'main'|'branch'|'session'|'ref', source?: string} $selector
     * @param  list<array<string, mixed>>  $turns
     * @param  list<array<string, mixed>>  $messages
     */
    public function __construct(
        public array $selector,
        public ?string $head,
        public ?string $root,
        public array $turns = [],
        public array $messages = [],
    ) {}
}
