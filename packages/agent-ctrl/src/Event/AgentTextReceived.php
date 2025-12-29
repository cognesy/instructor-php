<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Event;

use Cognesy\AgentCtrl\Enum\AgentType;
use Psr\Log\LogLevel;

/**
 * Emitted when text content is received from the agent.
 */
final class AgentTextReceived extends AgentEvent
{
    public string $logLevel = LogLevel::DEBUG;

    public function __construct(
        AgentType $agentType,
        public readonly string $text,
    ) {
        parent::__construct($agentType, [
            'text' => $text,
            'length' => strlen($text),
        ]);
    }

    #[\Override]
    public function __toString(): string
    {
        return sprintf(
            'Agent %s text (%d chars)',
            $this->agentType->value,
            strlen($this->text),
        );
    }
}
