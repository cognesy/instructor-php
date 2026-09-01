<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Workspace;

use Cognesy\Tell\Data\TellBranchInfo;

interface CanUseTellBranch extends CanMaintainTellConversation
{
    public function name(): string;

    public function info(): TellBranchInfo;

    public function pin(): CanUseTellRef;

    public function root(): CanUseTellRef;
}
