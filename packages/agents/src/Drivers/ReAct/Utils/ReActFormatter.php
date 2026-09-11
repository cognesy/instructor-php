<?php declare(strict_types=1);

namespace Cognesy\Agents\Drivers\ReAct\Utils;

use Cognesy\Agents\Data\ToolExecution;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Utils\Json\Json;
use Throwable;

final class ReActFormatter
{
    public function observationMessage(ToolExecution $execution): Message {
        $content = match (true) {
            $execution->hasError() => 'Observation: ERROR - ' . ($execution->error()?->getMessage() ?? ''),
            default => 'Observation: ' . Json::encode($execution->value()),
        };
        return new Message(role: 'user', content: $content);
    }

    public function decisionExtractionErrorMessages(Throwable $e): Messages {
        $assistant = new Message(role: 'assistant', content: 'Thought: Decision extraction failed.');
        $user = new Message(role: 'user', content: 'Observation: ERROR - ' . $e->getMessage());
        return Messages::empty()->appendMessage($assistant)->appendMessage($user);
    }
}
