<?php

namespace Cognesy\Instructor\Extras\Chat\Utils;

use Cognesy\Instructor\Utils\Messages\Messages;
//use Cognesy\Instructor\Utils\Messages\Script;
//use Cognesy\Instructor\Utils\Messages\Section;
use Cognesy\Instructor\Utils\Tokenizer;

class SplitMessages
{
    //public const MAIN_SECTION = 'main';
    //public const OVERFLOW_SECTION = 'overflow';

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

        //$mainSection = (new Section(self::MAIN_SECTION))->withMessages($keep->reversed());
        //$overflowSection = (new Section(self::OVERFLOW_SECTION))->withMessages($overflow->reversed());
        //return new Script($mainSection, $overflowSection);

        return [$keep->reversed(), $overflow->reversed()];
    }
}