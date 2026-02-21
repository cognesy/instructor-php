<?php declare(strict_types=1);

namespace Cognesy\Agents\Context\Compilers;

use Cognesy\Agents\Context\CanCompileMessages;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

final class ConversationWithCurrentToolTrace implements CanCompileMessages
{
    #[\Override]
    public function compile(AgentState $state): Messages
    {
        $allMessages = $state->store()->toMessages();
        $currentExecutionId = $state->execution()?->executionId()->toString();

        return $allMessages->filter(function (Message $msg) use ($currentExecutionId) {
            // Non-trace messages are conversation messages — always include
            if (!$msg->metadata()->get('is_trace')) {
                return true;
            }
            // Trace messages: include only if from current execution
            // No active execution means we're between executions — exclude all traces
            if ($currentExecutionId === null) {
                return false;
            }
            return $msg->metadata()->get('execution_id') === $currentExecutionId;
        });
    }
}
