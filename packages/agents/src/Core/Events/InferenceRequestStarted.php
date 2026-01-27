<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Events;

use DateTimeImmutable;

/**
 * Dispatched when an inference request is about to be sent to the LLM.
 * Use to distinguish "step preparation" from actual "waiting for LLM".
 */
final class InferenceRequestStarted extends AgentEvent
{
    public readonly DateTimeImmutable $startedAt;

    public function __construct(
        public readonly string $agentId,
        public readonly ?string $parentAgentId,
        public readonly int $stepNumber,
        public readonly int $messageCount,
        public readonly ?string $model = null,
    ) {
        $this->startedAt = new DateTimeImmutable();

        parent::__construct([
            'agentId' => $this->agentId,
            'parentAgentId' => $this->parentAgentId,
            'step' => $this->stepNumber,
            'messages' => $this->messageCount,
            'model' => $this->model,
        ]);
    }

    #[\Override]
    public function __toString(): string {
        $parentInfo = $this->parentAgentId ? sprintf(' [parent=%s]', substr($this->parentAgentId, 0, 8)) : '';
        $modelInfo = $this->model ? " model={$this->model}" : '';

        return sprintf(
            'Agent [%s]%s inference request started (messages=%d%s)',
            substr($this->agentId, 0, 8),
            $parentInfo,
            $this->messageCount,
            $modelInfo
        );
    }
}
