<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Event;

use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\Events\Event;

/**
 * Base class for all agent-ctrl events.
 */
abstract class AgentEvent extends Event
{
    public function __construct(
        public readonly AgentType $agentType,
        array $data = [],
    ) {
        parent::__construct(array_merge(['agentType' => $agentType->value], $data));
    }
}
