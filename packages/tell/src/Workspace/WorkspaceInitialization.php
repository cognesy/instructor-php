<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

final readonly class WorkspaceInitialization
{
    public function __construct(
        public TellWorkspace $workspace,
        public bool $created,
    ) {}
}
