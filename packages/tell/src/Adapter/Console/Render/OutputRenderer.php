<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Console\Render;

use Cognesy\Agents\AgentLoop;
use Cognesy\Tell\Core\Observation\TellEventNormalizer;
use Cognesy\Tell\Data\TellResult;

interface OutputRenderer
{
    public function attach(AgentLoop $loop, ?TellEventNormalizer $events = null): void;

    public function finish(TellResult $result): void;
}
