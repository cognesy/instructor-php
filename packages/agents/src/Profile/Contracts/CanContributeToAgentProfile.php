<?php declare(strict_types=1);

namespace Cognesy\Agents\Profile\Contracts;

use Cognesy\Agents\Profile\AgentProfile;

interface CanContributeToAgentProfile
{
    public function contributeToAgentProfile(AgentProfile $profile): AgentProfile;
}
