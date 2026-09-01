<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Agent;

use Cognesy\Tell\Core\Agent\TellAgentAssembly;

interface CanContributeTellAgent
{
    public function contribute(TellAgentAssembly $assembly): void;
}
