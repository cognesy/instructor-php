<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Serialization;

use Cognesy\Addons\Agent\Core\Data\AgentState;

interface CanSerializeAgentState
{
    public function serialize(AgentState $state): array;

    public function deserialize(array $data): AgentState;
}
