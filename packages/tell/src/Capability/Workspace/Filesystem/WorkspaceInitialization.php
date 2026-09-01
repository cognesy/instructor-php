<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Workspace\Filesystem;

final readonly class WorkspaceInitialization
{
    public function __construct(
        public WorkspaceState $workspace,
        public bool $created,
    ) {}
}
