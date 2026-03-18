<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Event;

use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\ValueObject\AgentCtrlExecutionId;
use Cognesy\Events\Event;

/**
 * Base class for all agent-ctrl events.
 */
abstract class AgentEvent extends Event
{
    public function __construct(
        public readonly AgentType $agentType,
        private readonly AgentCtrlExecutionId $executionId,
        array $data = [],
    ) {
        parent::__construct(array_merge([
            'agentType' => $agentType->value,
            'executionId' => (string) $executionId,
        ], $data));
    }

    public function executionId(): AgentCtrlExecutionId
    {
        return $this->executionId;
    }
}
