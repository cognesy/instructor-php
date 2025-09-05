<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat;

use Cognesy\Addons\Chat\Contracts\CanSummarizeMessages;
use Cognesy\Addons\Chat\Utils\SummarizeMessages;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Template\Script\Script;
use Cognesy\Utils\Tokenizer;

/**
 * @deprecated Use Cognesy\\Addons\\Chat\\Chat orchestrator with
 *             Pipelines\\BuildChatWithSummary to configure buffer/summarize
 *             processors. This class will be removed in a future release.
 */
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
        ?Script $script = null,
        int $maxChatTokens = 1024,
        int $maxBufferTokens = 1024,
        int $maxSummaryTokens = 1024,
        bool $autoBuffer = false,
        bool $autoSummarize = false,
        ?CanSummarizeMessages $summarizer = null,
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
        $this->script = $this->script->appendMessageToSection(self::SECTION_MAIN, $message);
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
        // clear buffer section
        $this->script = $this->script->replaceSection(
            self::SECTION_BUFFER,
            $this->script->withSection(self::SECTION_BUFFER)->section(self::SECTION_BUFFER)->clear()
        );
        // write summary section
        $this->script = $this->script->withSectionMessages(self::SECTION_SUMMARY, Messages::fromString($summary));
        return $this;
    }

    // INTERNAL ////////////////////////////////////////////////

    protected function overflowToBuffer(Script $script, int $tokens) : Script {
        if ($script->isEmpty()) {
            return $script;
        }

        $this->chatTokens = 0;
        $this->bufferTokens = 0;
        $limited = Messages::empty();
        $overflow = Messages::empty();
        $messages = $script->select([self::SECTION_BUFFER, self::SECTION_MAIN])->toMessages();
        $totalTokens = 0;
        foreach ($messages->reversed()->each() as $message) {
            $messageTokens = Tokenizer::tokenCount($message->toString());
            if ($totalTokens + $messageTokens <= $tokens) {
                $limited = $limited->appendMessage($message);
                $this->chatTokens += $messageTokens;
            } else {
                $overflow = $overflow->appendMessage($message);
                $this->bufferTokens += $messageTokens;
            }
            $totalTokens += $messageTokens;
        }

        $newScript = new Script();
        $newScript = $newScript->withSectionMessages(self::SECTION_MAIN, $limited->reversed());
        $newScript = $newScript->withSectionMessages(self::SECTION_BUFFER, $overflow->reversed());
        $newScript = $newScript->replaceSection(
            self::SECTION_SUMMARY,
            $newScript->withSection(self::SECTION_SUMMARY)
                ->section(self::SECTION_SUMMARY)
                ->copyFrom($script->section(self::SECTION_SUMMARY))
        );
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
