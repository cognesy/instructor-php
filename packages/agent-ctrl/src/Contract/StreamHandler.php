<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Contract;

use Cognesy\AgentCtrl\Dto\ToolCall;
use Cognesy\AgentCtrl\Dto\AgentResponse;
use Cognesy\AgentCtrl\Dto\StreamError;

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

    /**
     * Called when the agent emits a stream error event.
     */
    public function onError(StreamError $error): void;
}
