<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\Unified\Contract;

use Cognesy\Auxiliary\Agents\Unified\Dto\ToolCall;
use Cognesy\Auxiliary\Agents\Unified\Dto\AgentResponse;

/**
 * Contract for handling streaming events from an agent.
 */
interface StreamHandler
{
    /**
     * Called when text content is received.
     */
    public function onText(string $text): void;

    /**
     * Called when a tool is invoked.
     */
    public function onToolUse(ToolCall $toolCall): void;

    /**
     * Called when the agent completes its response.
     */
    public function onComplete(AgentResponse $response): void;
}
