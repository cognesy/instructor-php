<?php

namespace Cognesy\Addons\Chat\Utils;

use Cognesy\Utils\Messages\Messages;

class SplitMessages
{
    public function split(Messages $messages, int $tokenLimit): array {
        $limited = new Messages();
        $overflow = new Messages();
        $totalTokens = 0;
        foreach ($messages->reversed()->each() as $message) {
            $messageTokens = \Cognesy\Utils\Tokenizer::tokenCount($message->toString());
            if ($totalTokens + $messageTokens <= $tokenLimit) {
                $limited->appendMessage($message);
            } else {
                $overflow->appendMessage($message);
            }
            $totalTokens += $messageTokens;
        }

        return [$limited->reversed(), $overflow->reversed()];
    }
}