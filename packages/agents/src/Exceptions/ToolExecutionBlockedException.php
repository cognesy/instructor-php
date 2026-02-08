<?php declare(strict_types=1);

namespace Cognesy\Agents\Exceptions;

use Cognesy\Polyglot\Inference\Data\ToolCall;

class ToolExecutionBlockedException extends AgentException
{
    public function __construct(
        public ToolCall $toolCall,
        string $message,
        public string $hookName = '',
    ) {
        $info = "Tool call to '{$toolCall->name()}' (" . $toolCall->argsAsJson() . ") was blocked: " . $message;
        parent::__construct(message: $info);
    }
}
