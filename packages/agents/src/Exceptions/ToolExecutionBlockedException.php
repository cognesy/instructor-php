<?php declare(strict_types=1);

namespace Cognesy\Agents\Exceptions;

use Cognesy\Polyglot\Inference\Data\ToolCall;

class ToolExecutionBlockedException extends AgentException
{
    public function __construct(
        string $message,
        ToolCall $toolCall,
    ) {
        $info = "Tool call to '{$toolCall->name()}' (" . $toolCall->argsAsJson() . ") was blocked: " . $message;
        parent::__construct(message: $info);
    }
}