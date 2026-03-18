<?php declare(strict_types=1);

namespace Cognesy\Instructor\Data;

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

final readonly class StructuredPromptPlan
{
    public function __construct(
        private string $liveSystemPrompt,
        private Messages $liveConversation,
        private Messages $retryMessages,
        private string $cachedSystemPrompt,
        private Messages $cachedConversation,
    ) {}

    public function liveSystemPrompt(): string
    {
        return $this->liveSystemPrompt;
    }

    public function liveConversation(): Messages
    {
        return $this->liveConversation;
    }

    public function retryMessages(): Messages
    {
        return $this->retryMessages;
    }

    public function cachedSystemPrompt(): string
    {
        return $this->cachedSystemPrompt;
    }

    public function cachedConversation(): Messages
    {
        return $this->cachedConversation;
    }

    public function flattenedSystemPrompt(): string
    {
        return implode("\n\n", array_values(array_filter([
            trim($this->cachedSystemPrompt),
            trim($this->liveSystemPrompt),
        ], static fn(string $prompt): bool => $prompt !== '')));
    }

    public function toLiveMessages(): Messages
    {
        $messages = Messages::empty();

        if (trim($this->liveSystemPrompt) !== '') {
            $messages = $messages->appendMessage(Message::asSystem($this->liveSystemPrompt));
        }

        return $messages
            ->appendMessages($this->liveConversation)
            ->appendMessages($this->retryMessages);
    }

    public function toCachedMessages(): Messages
    {
        $messages = Messages::empty();

        if (trim($this->cachedSystemPrompt) !== '') {
            $messages = $messages->appendMessage(Message::asSystem($this->cachedSystemPrompt));
        }

        return $messages->appendMessages($this->cachedConversation);
    }

    public function toFlattenedMessages(): Messages
    {
        $messages = Messages::empty();
        $systemPrompt = $this->flattenedSystemPrompt();

        if ($systemPrompt !== '') {
            $messages = $messages->appendMessage(Message::asSystem($systemPrompt));
        }

        return $messages
            ->appendMessages($this->cachedConversation)
            ->appendMessages($this->liveConversation)
            ->appendMessages($this->retryMessages);
    }
}
