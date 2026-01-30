<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Tools;

use Stringable;

/**
 * Value object representing a tool call blocked by hooks.
 */
final readonly class ToolCallBlockedError implements Stringable
{
    public function __construct(
        private string $toolName,
        private string $reason,
    ) {}

    public function toolName(): string
    {
        return $this->toolName;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function __toString(): string
    {
        return "Tool call '{$this->toolName}' was blocked: {$this->reason}";
    }
}
