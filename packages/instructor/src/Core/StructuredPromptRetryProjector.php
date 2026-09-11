<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\ToolCall;
use Cognesy\Messages\ToolResult;

final class StructuredPromptRetryProjector
{
    public function project(?Message $response, string $feedback): Messages
    {
        $messages = match (true) {
            $response === null => Messages::empty(),
            $response->isEmpty() => Messages::empty(),
            default => new Messages($response),
        };

        if ($feedback === '') {
            return $messages;
        }

        if ($response === null || !$response->hasToolCalls()) {
            return $messages->appendMessage(Message::asUser($feedback));
        }

        $toolResults = array_map(
            static fn(ToolCall $call): Message => Message::asTool('')->withToolResult(ToolResult::error(
                content: $feedback,
                callId: $call->id(),
                toolName: $call->name(),
            )),
            $response->toolCalls()->all(),
        );

        return $messages->appendMessages($toolResults);
    }
}
