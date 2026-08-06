<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Event;

use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\ValueObject\AgentCtrlExecutionId;
use Cognesy\AgentCtrl\ValueObject\AgentSessionId;
use Psr\Log\LogLevel;

/**
 * Emitted when agent execution begins.
 *
 * `sessionId` is set only when the caller resumed a specific session, which is the earliest
 * point the runtime honestly knows one. A fresh run has no session until the agent reports it,
 * and `continueSession()` names "the most recent" rather than an identifier — both stay null
 * here rather than being filled with a placeholder. The execution root is still keyed on the
 * execution id, so two runs sharing a session remain distinct traces that merely correlate.
 */
final class AgentExecutionStarted extends AgentEvent
{
    public string $logLevel = LogLevel::INFO;

    private ?AgentSessionId $sessionId;

    public function __construct(
        AgentType $agentType,
        AgentCtrlExecutionId $executionId,
        public readonly string $prompt,
        public readonly ?string $model = null,
        public readonly ?string $workingDirectory = null,
        AgentSessionId|string|null $sessionId = null,
    ) {
        $this->sessionId = match (true) {
            $sessionId instanceof AgentSessionId => $sessionId,
            is_string($sessionId) && $sessionId !== '' => AgentSessionId::fromString($sessionId),
            default => null,
        };

        parent::__construct($agentType, $executionId, [
            'prompt' => $this->truncatePrompt($prompt),
            'model' => $model,
            'workingDirectory' => $workingDirectory,
            'sessionId' => $this->sessionId !== null ? (string) $this->sessionId : null,
        ]);
    }

    public function sessionId(): ?AgentSessionId
    {
        return $this->sessionId;
    }

    #[\Override]
    public function __toString(): string
    {
        return sprintf(
            'Agent %s started%s',
            $this->agentType->value,
            $this->model ? " (model: {$this->model})" : '',
        );
    }

    private function truncatePrompt(string $prompt, int $maxLength = 100): string
    {
        if (strlen($prompt) <= $maxLength) {
            return $prompt;
        }
        return substr($prompt, 0, $maxLength) . '...';
    }
}
