<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Serialization;

use Cognesy\Addons\Agent\Core\Data\AgentState;

interface CanSerializeAgentState
{
    public function serialize(AgentState $state): array;

    public function deserialize(array $data): AgentState;
}
