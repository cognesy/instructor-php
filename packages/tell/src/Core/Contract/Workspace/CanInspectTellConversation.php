<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Workspace;

use Cognesy\Tell\Data\TellContext;
use Cognesy\Tell\Data\TellConversationView;
use Cognesy\Tell\Data\TellRequest;

interface CanInspectTellConversation
{
    public function history(int $limit = 20, bool $full = false): TellConversationView;

    public function transcript(bool $full = false): TellConversationView;

    public function context(?TellRequest $request = null): TellContext;
}
