<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Event;

use Cognesy\AgentCtrl\Dto\ToolCall;
use Cognesy\AgentCtrl\Enum\AgentType;
use Psr\Log\LogLevel;

/**
 * Emitted when the agent uses a tool.
 */
final class AgentToolUsed extends AgentEvent
{
    public string $logLevel = LogLevel::DEBUG;

    public function __construct(
        AgentType $agentType,
        public readonly string $tool,
        public readonly array $input,
        public readonly ?string $output = null,
        public readonly ?string $callId = null,
    ) {
        parent::__construct($agentType, [
            'tool' => $tool,
            'input' => $input,
            'output' => $output,
            'callId' => $callId,
        ]);
    }

    public static function fromToolCall(AgentType $agentType, ToolCall $toolCall): self
    {
        return new self(
            agentType: $agentType,
            tool: $toolCall->tool,
            input: $toolCall->input,
            output: $toolCall->output,
            callId: $toolCall->callId,
        );
    }

    #[\Override]
    public function __toString(): string
    {
        return sprintf(
            'Agent %s tool: %s',
            $this->agentType->value,
            $this->tool,
        );
    }
}
