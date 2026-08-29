<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

final readonly class WorkspaceInitialization
{
    public function __construct(
        public WorkspaceState $workspace,
        public bool $created,
    ) {}
}
