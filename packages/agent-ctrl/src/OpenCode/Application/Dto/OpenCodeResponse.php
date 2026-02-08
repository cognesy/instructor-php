<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenCode\Application\Dto;

use Cognesy\AgentCtrl\Common\Collection\DecodedObjectCollection;
use Cognesy\AgentCtrl\OpenCode\Domain\Value\TokenUsage;
use Cognesy\Sandbox\Data\ExecResult;

/**
 * Response from OpenCode CLI execution
 */
final readonly class OpenCodeResponse
{
    public function __construct(
        private ExecResult $result,
        private DecodedObjectCollection $decoded,
        private ?string $sessionId = null,
        private ?string $messageId = null,
        private string $messageText = '',
        private ?TokenUsage $usage = null,
        private ?float $cost = null,
    ) {}

    /**
     * Get the exit code from CLI execution
     */
    public function exitCode(): int
    {
        return $this->result->exitCode();
    }

    /**
     * Get raw stdout from CLI
     */
    public function stdout(): string
    {
        return $this->result->stdout();
    }

    /**
     * Get raw stderr from CLI
     */
    public function stderr(): string
    {
        return $this->result->stderr();
    }

    /**
     * Get the underlying execution result
     */
    public function result(): ExecResult
    {
        return $this->result;
    }

    /**
     * Get decoded JSON objects from output
     */
    public function decoded(): DecodedObjectCollection
    {
        return $this->decoded;
    }

    /**
     * Get session identifier
     */
    public function sessionId(): ?string
    {
        return $this->sessionId;
    }

    /**
     * Get message identifier
     */
    public function messageId(): ?string
    {
        return $this->messageId;
    }

    /**
     * Get accumulated text from all text events
     */
    public function messageText(): string
    {
        return $this->messageText;
    }

    /**
     * Get token usage statistics (from final step_finish)
     */
    public function usage(): ?TokenUsage
    {
        return $this->usage;
    }

    /**
     * Get total cost in USD (accumulated from all step_finish events)
     */
    public function cost(): ?float
    {
        return $this->cost;
    }

    /**
     * Check if execution was successful
     */
    public function isSuccess(): bool
    {
        return $this->result->exitCode() === 0;
    }
}
