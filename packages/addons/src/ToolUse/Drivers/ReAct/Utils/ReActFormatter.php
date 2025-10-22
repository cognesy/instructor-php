<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Drivers\ReAct\Utils;

use Cognesy\Addons\ToolUse\Data\ToolExecution;
use Cognesy\Addons\ToolUse\Drivers\ReAct\Contracts\Decision;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Utils\Json\Json;

final class ReActFormatter
{
    public function assistantThoughtActionMessage(Decision $decision) : Message {
        $content = "Thought: " . $decision->thought();
        if ($decision->isCall()) {
            $content .= "\nAction: " . ($decision->tool() ?? '');
            $content .= "\nAction Input: " . Json::encode($decision->args());
        } else {
            $content .= "\nAction: final_answer";
        }
        return new Message(role: 'assistant', content: $content);
    }

    public function observationMessage(ToolExecution $execution) : Message {
        $content = match (true) {
            $execution->hasError() => 'Observation: ERROR - ' . ($execution->error()?->getMessage() ?? ''),
            default => 'Observation: ' . Json::encode($execution->value()),
        };
        return new Message(role: 'user', content: $content);
    }

    public function decisionExtractionErrorMessages(\Throwable $e) : Messages {
        $assistant = new Message(role: 'assistant', content: 'Thought: Decision extraction failed.');
        $user = new Message(role: 'user', content: 'Observation: ERROR - '.$e->getMessage());
        return Messages::empty()->appendMessage($assistant)->appendMessage($user);
    }
}
