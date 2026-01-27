<?php declare(strict_types=1);

namespace Cognesy\Agents\Serialization;

use Cognesy\Agents\Core\Data\AgentState;

interface CanSerializeAgentState
{
    public function serialize(AgentState $state): array;

    public function deserialize(array $data): AgentState;
}
