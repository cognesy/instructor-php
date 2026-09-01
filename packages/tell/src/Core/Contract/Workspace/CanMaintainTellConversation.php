<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Workspace;

use Cognesy\Tell\Data\TellClearResult;
use Cognesy\Tell\Data\TellCompactionResult;
use Cognesy\Tell\Data\TellRequest;

interface CanMaintainTellConversation extends CanInspectTellConversation
{
    public function clear(): TellClearResult;

    public function compact(TellRequest $request, string $hint = ''): TellCompactionResult;
}
