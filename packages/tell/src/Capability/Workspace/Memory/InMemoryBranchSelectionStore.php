<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Workspace\Memory;

use Cognesy\Tell\Core\Contract\Workspace\CanUseTellBranchSelectionStore;
use Cognesy\Tell\Core\Workspace\Branch\Storage\BranchCurrentSelection;
use Override;

final class InMemoryBranchSelectionStore implements CanUseTellBranchSelectionStore
{
    private BranchCurrentSelection $selection;

    public function __construct() {
        $this->selection = BranchCurrentSelection::main();
    }

    #[Override]
    public function read(): BranchCurrentSelection {
        return $this->selection;
    }

    #[Override]
    public function write(string $branch): BranchCurrentSelection {
        return $this->selection = new BranchCurrentSelection($branch);
    }
}
