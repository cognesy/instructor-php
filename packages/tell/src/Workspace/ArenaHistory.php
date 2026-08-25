<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Messages\Messages;
use Cognesy\Tell\Canonical\CanonicalHash;

/**
 * The provider-independent conversation restored from one immutable arena head.
 */
final readonly class ArenaHistory
{
    /** @param list<ArenaHistoryTurn> $turns */
    public function __construct(
        public ?CanonicalHash $referenceHead,
        public ?CanonicalHash $turnHead,
        public ?CanonicalHash $root,
        public Messages $messages,
        public array $turns = [],
    ) {}
}
