<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Agent;

use Cognesy\Agents\Template\AgentDefinitionRegistry;

interface CanLoadTellAgentDefinitions
{
    public function definitions(string $projectPath): AgentDefinitionRegistry;
}
