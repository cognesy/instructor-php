<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Event;

use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\ValueObject\AgentCtrlExecutionId;
use Psr\Log\LogLevel;
use Throwable;

/**
 * Emitted when an error occurs during agent execution.
 */
final class AgentErrorOccurred extends AgentEvent
{
    public string $logLevel = LogLevel::ERROR;

    public function __construct(
        AgentType $agentType,
        AgentCtrlExecutionId $executionId,
        public readonly string $error,
        public readonly ?string $errorClass = null,
        public readonly ?int $exitCode = null,
    ) {
        parent::__construct($agentType, $executionId, [
            'error' => $error,
            'errorClass' => $errorClass,
            'exitCode' => $exitCode,
        ]);
    }

    public static function fromException(
        AgentType $agentType,
        AgentCtrlExecutionId $executionId,
        Throwable $e,
    ): self
    {
        return new self(
            agentType: $agentType,
            executionId: $executionId,
            error: $e->getMessage(),
            errorClass: $e::class,
            exitCode: is_int($e->getCode()) && $e->getCode() !== 0 ? $e->getCode() : null,
        );
    }

    #[\Override]
    public function __toString(): string
    {
        return sprintf(
            'Agent %s error: %s',
            $this->agentType->value,
            $this->error,
        );
    }
}
