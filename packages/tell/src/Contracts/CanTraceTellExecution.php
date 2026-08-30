<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Agents\AgentLoop;
use Cognesy\Tell\Data\TellRequest;

interface CanTraceTellExecution
{
    public function attach(AgentLoop $loop, TellRequest $request): void;
}
