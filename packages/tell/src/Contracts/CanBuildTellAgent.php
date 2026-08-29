<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Tell\Data\TellRequest;

interface CanBuildTellAgent
{
    public function definition(TellRequest $request): AgentDefinition;

    public function build(
        TellRequest $request,
        ?CanProvideCancellationSignal $cancellation = null,
    ): AgentLoop;
}
