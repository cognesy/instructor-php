<?php

namespace Cognesy\Instructor\Extras\Chat\Utils;

use Cognesy\Instructor\Utils\Messages\Messages;
use Cognesy\Instructor\Utils\Tokenizer;

class SplitMessages
{
    public function split(Messages $messages, int $tokenLimit): array {
        $keep = new Messages();
        $overflow = new Messages();
        $tokens = 0;
        foreach ($messages->reversed()->each() as $message) {
            $messageTokens = Tokenizer::tokenCount($message->toString());
            if ($tokens + $messageTokens <= $tokenLimit) {
                $keep->appendMessage($message);
                $tokens += $messageTokens;
            } else {
                $overflow->appendMessage($message);
            }
        }
        return [$keep->reversed(), $overflow->reversed()];
    }
}