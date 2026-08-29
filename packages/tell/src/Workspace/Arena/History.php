<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Arena;

use Cognesy\Messages\Messages;

/**
 * The provider-independent conversation restored from one immutable arena head.
 */
final readonly class History
{
    /** @param list<HistoryTurn> $turns */
    public function __construct(
        public ?ObjectHash $referenceHead,
        public ?ObjectHash $turnHead,
        public ?ObjectHash $root,
        public Messages $messages,
        public array $turns = [],
    ) {}
}
