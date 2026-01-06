<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Contracts;

use Cognesy\Utils\Result\Result;

interface AgentFactory
{
    public function create(string $agentName, array $config = []): Result;
}
