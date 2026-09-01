<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Workspace;

use Cognesy\Tell\Core\Contract\Workspace\CanUseTellBranchConfigurationStore;
use Cognesy\Tell\Core\Contract\Workspace\CanUseTellBranchSelectionStore;
use Cognesy\Tell\Core\Contract\Workspace\CanUseTellWorkspaceArena;
use Cognesy\Tell\Data\TellWorkspaceInfo;

final readonly class TellWorkspaceContext
{
    public function __construct(
        public TellWorkspaceInfo $info,
        public CanUseTellWorkspaceArena $arena,
        public CanUseTellBranchSelectionStore $branchSelection,
        public CanUseTellBranchConfigurationStore $branchConfiguration,
    ) {}
}
