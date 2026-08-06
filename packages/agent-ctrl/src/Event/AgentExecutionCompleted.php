<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Event;

use Cognesy\AgentCtrl\Dto\AgentResponse;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\ValueObject\AgentCtrlExecutionId;
use Cognesy\AgentCtrl\ValueObject\AgentSessionId;
use Psr\Log\LogLevel;

/**
 * Emitted when agent execution completes.
 *
 * By this point the agent has reported its session, so the close of the execution span carries
 * it even for a fresh run that had none at start. See AgentExecutionStarted for the start-side
 * rule.
 */
final class AgentExecutionCompleted extends AgentEvent
{
    public string $logLevel = LogLevel::INFO;

    private ?AgentSessionId $sessionId;

    public function __construct(
        AgentType $agentType,
        AgentCtrlExecutionId $executionId,
        public readonly int $exitCode,
        public readonly int $toolCallCount,
        public readonly ?float $cost = null,
        public readonly ?int $inputTokens = null,
        public readonly ?int $outputTokens = null,
        public readonly string $text = '',
        AgentSessionId|string|null $sessionId = null,
    ) {
        $this->sessionId = match (true) {
            $sessionId instanceof AgentSessionId => $sessionId,
            is_string($sessionId) && $sessionId !== '' => AgentSessionId::fromString($sessionId),
            default => null,
        };

        parent::__construct($agentType, $executionId, [
            'exitCode' => $exitCode,
            'toolCallCount' => $toolCallCount,
            'cost' => $cost,
            'inputTokens' => $inputTokens,
            'outputTokens' => $outputTokens,
            'sessionId' => $this->sessionId !== null ? (string) $this->sessionId : null,
        ]);
    }

    public function sessionId(): ?AgentSessionId
    {
        return $this->sessionId;
    }

    public static function fromResponse(AgentResponse $response): self
    {
        return new self(
            agentType: $response->agentType,
            executionId: $response->executionId(),
            exitCode: $response->exitCode,
            toolCallCount: count($response->toolCalls),
            cost: $response->cost,
            inputTokens: $response->usage?->input,
            outputTokens: $response->usage?->output,
            text: $response->text,
            sessionId: $response->sessionId(),
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
