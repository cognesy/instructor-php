<?php

namespace Cognesy\Instructor\Extras\Chat\Utils;

use Cognesy\Instructor\Utils\Messages\Messages;
use Cognesy\Instructor\Utils\Tokenizer;

class SplitMessages
{
    public function split(Messages $messages, int $tokenLimit): array {
        $limited = new Messages();
        $overflow = new Messages();
        $totalTokens = 0;
        foreach ($messages->reversed()->each() as $message) {
            $messageTokens = Tokenizer::tokenCount($message->toString());
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