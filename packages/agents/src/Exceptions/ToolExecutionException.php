<?php declare(strict_types=1);

namespace Cognesy\Agents\Exceptions;

use Cognesy\Polyglot\Inference\Data\ToolCall;
use Throwable;

class ToolExecutionException extends AgentException
{
    public function __construct(
        string $message,
        ToolCall $toolCall,
        ?Throwable $previous = null,
    ) {
        $info = "Error executing tool '{$toolCall->name()}'(" . $toolCall->argsAsJson() . "): " . $message;
        parent::__construct(
            message: $info,
            previous: $previous,
        );
    }
}