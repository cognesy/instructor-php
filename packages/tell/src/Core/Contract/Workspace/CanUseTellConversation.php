<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Workspace;

use Cognesy\Tell\Data\TellProgress;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Data\TellResult;
use Generator;

interface CanUseTellConversation extends CanMaintainTellConversation
{
    public function send(TellRequest $request): TellResult;

    /** @return Generator<int, TellProgress, mixed, TellResult> */
    public function sendStream(TellRequest $request): Generator;

}
