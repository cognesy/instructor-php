<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Summarization\Utils;

use Cognesy\Messages\Messages;
use Cognesy\Utils\Tokenization\Contracts\CanCountTokens;
use Cognesy\Utils\Tokenizer;

class SplitMessages
{
    public function __construct(
        private ?CanCountTokens $tokenizer = null,
    ) {}

    public function split(Messages $messages, int $tokenLimit): array {
        $tokenizer = $this->tokenizer();
        $limited = Messages::empty();
        $overflow = Messages::empty();

        $totalTokens = 0;
        foreach ($messages->reversed()->each() as $message) {
            $messageTokens = $tokenizer->tokenCount($message->toString());
            if ($totalTokens + $messageTokens <= $tokenLimit) {
                $limited = $limited->appendMessage($message);
            } else {
                $overflow = $overflow->appendMessage($message);
            }
            $totalTokens += $messageTokens;
        }

        return [$limited->reversed(), $overflow->reversed()];
    }

    /**
     * Resolved on use rather than in the constructor: the default tokenizer loads
     * a multi-megabyte vocabulary, and splitting is often configured but never
     * reached. Tokenizer::default() memoizes, so repeating the call is free.
     */
    private function tokenizer(): CanCountTokens {
        return $this->tokenizer ?? Tokenizer::default();
    }
}
