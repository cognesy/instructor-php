<?php declare(strict_types=1);

namespace Cognesy\Agents\Tool\Contracts;

use Cognesy\Polyglot\Inference\Data\ToolCall;

/**
 * Interface for tools that need access to their invocation context.
 * Useful for correlation/tracing (e.g., subagent tools that emit events).
 */
interface CanAccessToolCall
{
    public function withToolCall(ToolCall $toolCall): static;
}
