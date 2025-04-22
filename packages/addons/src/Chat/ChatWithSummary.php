<?php

namespace Cognesy\Addons\Chat;

use Cognesy\Addons\Chat\Contracts\CanSummarizeMessages;
use Cognesy\Addons\Chat\Utils\SummarizeMessages;
use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;
use Cognesy\Utils\Messages\Script;
use Cognesy\Utils\Tokenizer;

class ChatWithSummary
{
    const SECTION_MAIN = 'main';
    const SECTION_BUFFER = 'buffer';
    const SECTION_SUMMARY = 'summary';

    private CanSummarizeMessages $summarizer;

    private Script $script;

    private bool $autoBuffer;
    private bool $autoSummarize;

    private int $maxChatTokens;
    private int $maxBufferTokens;
    private int $maxSummaryTokens;

    private int $chatTokens = 0;
    private int $bufferTokens = 0;
    private int $summaryTokens = 0;

    public function __construct(
        Script $script = null,
        int $maxChatTokens = 1024,
        int $maxBufferTokens = 1024,
        int $maxSummaryTokens = 1024,
        bool $autoBuffer = false,
        bool $autoSummarize = false,
        CanSummarizeMessages $summarizer = null,
    ) {
        $this->script = $script ?? new Script();
        $this->maxChatTokens = $maxChatTokens;
        $this->maxBufferTokens = $maxBufferTokens;
        $this->maxSummaryTokens = $maxSummaryTokens;
        $this->autoBuffer = $autoBuffer;
        $this->autoSummarize = $autoSummarize;
        $this->summarizer = $summarizer ?? new SummarizeMessages();
    }

    public static function fromMessages(Messages $messages) : self {
        $chat = new self();
        $chat->appendMessages($messages);
        return $chat;
    }

    public function messages() : Messages {
        return $this->script
            ->select([self::SECTION_SUMMARY, self::SECTION_BUFFER, self::SECTION_MAIN])
            ->toMessages();
    }

    public function script() : Script {
        return $this->script;
    }

    public function tokens() : int {
        return $this->chatTokens + $this->bufferTokens + $this->summaryTokens;
    }

    public function appendMessages(Messages $messages) : self {
        foreach ($messages->each() as $message) {
            $this->appendMessage($message);
        }
        return $this;
    }

    public function appendMessage(Message $message) : self {
        $messageTokens = Tokenizer::tokenCount($message->toString());
        $this->script->section(self::SECTION_MAIN)->appendMessage($message);
        $this->chatTokens += $messageTokens;
        if ($this->autoBuffer && ($this->chatTokens > $this->maxChatTokens)) {
            $this->buffer();
        }
        return $this;
    }

    public function buffer() : self {
        $this->script = $this->overflowToBuffer($this->script, $this->maxChatTokens);
        if ($this->autoSummarize && ($this->bufferTokens > $this->maxBufferTokens)) {
            $this->summarize();
        }
        return $this;
    }

    public function summarize() : self {
        $summary = $this->makeSummary($this->script, $this->maxSummaryTokens);
        $this->summaryTokens = Tokenizer::tokenCount($summary);
        $this->script->section(self::SECTION_BUFFER)->clear();
        $this->script->section(self::SECTION_SUMMARY)->withMessages(Messages::fromString($summary));
        return $this;
    }

    // INTERNAL ////////////////////////////////////////////////

    protected function overflowToBuffer(Script $script, int $tokens) : Script {
        if ($script->isEmpty()) {
            return $script;
        }

        $this->chatTokens = 0;
        $this->bufferTokens = 0;
        $limited = new Messages();
        $overflow = new Messages();
        $messages = $script->select([self::SECTION_BUFFER, self::SECTION_MAIN])->toMessages();
        $totalTokens = 0;
        foreach ($messages->reversed()->each() as $message) {
            $messageTokens = Tokenizer::tokenCount($message->toString());
            if ($totalTokens + $messageTokens <= $tokens) {
                $limited->appendMessage($message);
                $this->chatTokens += $messageTokens;
            } else {
                $overflow->appendMessage($message);
                $this->bufferTokens += $messageTokens;
            }
            $totalTokens += $messageTokens;
        }

        $newScript = new Script();
        $newScript->section(self::SECTION_MAIN)->appendMessages($limited->reversed());
        $newScript->section(self::SECTION_BUFFER)->appendMessages($overflow->reversed());
        $newScript->section(self::SECTION_SUMMARY)->copyFrom($script->section(self::SECTION_SUMMARY));
        return $newScript;
    }

    protected function makeSummary(Script $script, int $tokens) : string {
        $messages = $script->select([self::SECTION_SUMMARY, self::SECTION_BUFFER])->toMessages();
        if ($messages->isEmpty()) {
            return '';
        }
        return $this->summarizer->summarize($messages, $tokens);
    }
}