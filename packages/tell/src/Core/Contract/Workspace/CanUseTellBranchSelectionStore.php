<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Workspace;

use Cognesy\Tell\Core\Workspace\Branch\Storage\BranchCurrentSelection;

interface CanUseTellBranchSelectionStore
{
    public function read(): BranchCurrentSelection;

    public function write(string $branch): BranchCurrentSelection;
}
