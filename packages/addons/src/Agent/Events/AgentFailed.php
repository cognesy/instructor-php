<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/instructor-agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Events;

use Cognesy\Addons\Agent\Core\Enums\AgentStatus;
use Cognesy\Addons\Agent\Exceptions\AgentException;
use Cognesy\Polyglot\Inference\Data\Usage;
use DateTimeImmutable;

/**
 * Dispatched when an agent fails with an exception.
 * Contains exception details and execution state at failure.
 */
final class AgentFailed extends AgentEvent
{
    public readonly DateTimeImmutable $failedAt;

    public function __construct(
        public readonly string $agentId,
        public readonly ?string $parentAgentId,
        public readonly AgentException $exception,
        public readonly AgentStatus $status,
        public readonly int $stepsCompleted,
        public readonly Usage $totalUsage,
        public readonly ?string $errors,
    ) {
        $this->failedAt = new DateTimeImmutable();

        parent::__construct([
            'agentId' => $this->agentId,
            'parentAgentId' => $this->parentAgentId,
            'error' => $this->exception->getMessage(),
            'errorType' => get_class($this->exception),
            'status' => $this->status->value,
            'steps' => $this->stepsCompleted,
            'usage' => $this->totalUsage->toArray(),
            'errors' => $this->errors,
        ]);
    }

    #[\Override]
    public function __toString(): string {
        $parentInfo = $this->parentAgentId ? sprintf(' [parent=%s]', substr($this->parentAgentId, 0, 8)) : '';

        return sprintf(
            'Agent [%s]%s failed after %d steps - %s',
            substr($this->agentId, 0, 8),
            $parentInfo,
            $this->stepsCompleted,
            $this->exception->getMessage()
        );
    }
}