<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Utils;

use Cognesy\Messages\Messages;

class SplitMessages
{
    public function split(Messages $messages, int $tokenLimit): array {
        $limited = Messages::empty();
        $overflow = Messages::empty();
        $totalTokens = 0;
        foreach ($messages->reversed()->each() as $message) {
            $messageTokens = \Cognesy\Utils\Tokenizer::tokenCount($message->toString());
            if ($totalTokens + $messageTokens <= $tokenLimit) {
                $limited = $limited->appendMessage($message);
            } else {
                $overflow = $overflow->appendMessage($message);
            }
            $totalTokens += $messageTokens;
        }

        return [$limited->reversed(), $overflow->reversed()];
    }
}
