<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Event;

use Cognesy\AgentCtrl\Dto\AgentResponse;
use Cognesy\AgentCtrl\Enum\AgentType;
use Psr\Log\LogLevel;

/**
 * Emitted when agent execution completes.
 */
final class AgentExecutionCompleted extends AgentEvent
{
    public string $logLevel = LogLevel::INFO;

    public function __construct(
        AgentType $agentType,
        public readonly int $exitCode,
        public readonly int $toolCallCount,
        public readonly ?float $cost = null,
        public readonly ?int $inputTokens = null,
        public readonly ?int $outputTokens = null,
    ) {
        parent::__construct($agentType, [
            'exitCode' => $exitCode,
            'toolCallCount' => $toolCallCount,
            'cost' => $cost,
            'inputTokens' => $inputTokens,
            'outputTokens' => $outputTokens,
        ]);
    }

    public static function fromResponse(AgentResponse $response): self
    {
        return new self(
            agentType: $response->agentType,
            exitCode: $response->exitCode,
            toolCallCount: count($response->toolCalls),
            cost: $response->cost,
            inputTokens: $response->usage?->input,
            outputTokens: $response->usage?->output,
        );
    }

    #[\Override]
    public function __toString(): string
    {
        $parts = [
            "Agent {$this->agentType->value} completed",
            "(exit: {$this->exitCode})",
        ];

        if ($this->toolCallCount > 0) {
            $parts[] = "tools: {$this->toolCallCount}";
        }

        if ($this->cost !== null) {
            $parts[] = sprintf('cost: $%.4f', $this->cost);
        }

        return implode(' ', $parts);
    }
}
