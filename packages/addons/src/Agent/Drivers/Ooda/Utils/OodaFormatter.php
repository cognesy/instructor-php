<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Drivers\Ooda\Utils;

use Cognesy\Addons\Agent\Data\AgentExecution;
use Cognesy\Addons\Agent\Drivers\Ooda\Data\OodaDecision;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

/**
 * Formats OODA loop messages.
 */
final class OodaFormatter
{
    /**
     * Format the assistant's OODA decision as a message.
     */
    public function assistantOodaMessage(OodaDecision $decision): Message {
        return Message::asAssistant($decision->toFormattedOutput());
    }

    /**
     * Format tool execution result as observation message.
     */
    public function observationMessage(AgentExecution $execution): Message {
        $name = $execution->name();
        if ($execution->hasError()) {
            $error = $execution->error()?->getMessage() ?? 'Unknown error';
            return Message::asUser("[Observation] Tool '{$name}' failed: {$error}");
        }
        $result = $execution->value();
        $text = is_string($result) ? $result : json_encode($result, JSON_UNESCAPED_SLASHES);
        return Message::asUser("[Observation] Tool '{$name}' returned:\n{$text}");
    }

    /**
     * Format error message for decision extraction failure.
     */
    public function decisionExtractionErrorMessages(\Throwable $e): Messages {
        $content = "[Error] Failed to extract OODA decision: {$e->getMessage()}";
        return Messages::fromArray([['role' => 'user', 'content' => $content]]);
    }
}
