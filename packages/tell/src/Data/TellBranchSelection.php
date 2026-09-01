<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

/** The branch selected for subsequent workspace-backed Tell requests. */
final readonly class TellBranchSelection
{
    /** @param 'current'|'invocation' $source */
    public function __construct(
        public string $name,
        public string $source,
    ) {}
}
