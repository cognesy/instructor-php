<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Lifecycle;

use Cognesy\Polyglot\Inference\Data\ToolCall;

/**
 * Decision returned by toolUsing() observer method.
 *
 * Can indicate to proceed (with possibly modified tool call) or block execution.
 */
final readonly class ToolUseDecision
{
    private function __construct(
        private bool $blocked,
        private ?ToolCall $toolCall,
        private ?string $reason,
    ) {}

    public static function proceed(ToolCall $toolCall): self
    {
        return new self(
            blocked: false,
            toolCall: $toolCall,
            reason: null,
        );
    }

    public static function block(string $reason): self
    {
        return new self(
            blocked: true,
            toolCall: null,
            reason: $reason,
        );
    }

    public function isBlocked(): bool
    {
        return $this->blocked;
    }

    public function isProceed(): bool
    {
        return !$this->blocked;
    }

    public function toolCall(): ?ToolCall
    {
        return $this->toolCall;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }
}
