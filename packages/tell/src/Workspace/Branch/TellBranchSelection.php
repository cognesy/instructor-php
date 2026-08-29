<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Branch;

/** The branch selected for subsequent workspace-backed Tell requests. */
final readonly class TellBranchSelection
{
    public function __construct(
        public string $name,
        public string $source,
    ) {}
}
