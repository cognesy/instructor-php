<?php

namespace Cognesy\Addons\Chat\Processors;

use Cognesy\Addons\Chat\Contracts\CanSummarizeMessages;
use Cognesy\Addons\Chat\Contracts\ScriptProcessor;
use Cognesy\Template\Script\Script;
use Cognesy\Utils\Messages\Messages;
use Cognesy\Utils\Tokenizer;

class SummarizeBuffer implements ScriptProcessor {
    private string $sourceSection;
    private string $targetSection;
    private int $maxBufferTokens;
    private int $maxSummaryTokens;
    private CanSummarizeMessages $summarizer;

    public function __construct(
        string               $sourceSection,
        string               $targetSection,
        int                  $maxBufferTokens,
        int                  $maxSummaryTokens,
        CanSummarizeMessages $summarizer
    ) {
        $this->sourceSection = $sourceSection;
        $this->targetSection = $targetSection;
        $this->maxBufferTokens = $maxBufferTokens;
        $this->maxSummaryTokens = $maxSummaryTokens;
        $this->summarizer = $summarizer;
    }

    public function shouldProcess(Script $script): bool {
        $tokens = Tokenizer::tokenCount(
            $script->section($this->sourceSection)->toMessages()->toString()
        );
        return $tokens > $this->maxBufferTokens;
    }

    public function process(Script $script): Script {
        if (!$this->shouldProcess($script)) {
            return $script;
        }

        $messages = $script->section($this->sourceSection)->toMessages();
        $summary = $this->summarizer->summarize($messages, $this->maxSummaryTokens);

        $newScript = new Script();
        foreach ($script->sections() as $section) {
            $sectionName = $section->name();
            if ($sectionName === $this->sourceSection) {
                $newScript->section($sectionName)->clear();
            } elseif ($sectionName === $this->targetSection) {
                $newScript->section($sectionName)->withMessages(Messages::fromString($summary));
            } else {
                $newScript->section($sectionName)->copyFrom($script->section($sectionName));
            }
        }

        return $newScript;
    }
}