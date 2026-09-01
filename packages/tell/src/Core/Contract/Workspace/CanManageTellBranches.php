<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Workspace;

use Cognesy\Tell\Data\TellBranchInfo;
use Cognesy\Tell\Data\TellBranchReset;
use Cognesy\Tell\Data\TellBranchSelection;

interface CanManageTellBranches
{
    /** @return list<TellBranchInfo> */
    public function list(bool $full = false): array;

    public function show(string $name): TellBranchInfo;

    public function current(): TellBranchSelection;

    public function create(string $name, ?string $from = null, bool $empty = false): TellBranchInfo;

    public function checkout(string $name): TellBranchSelection;

    public function reset(string $name, int $steps): TellBranchReset;

    public function resetTo(string $name, string $hash): TellBranchReset;
}
