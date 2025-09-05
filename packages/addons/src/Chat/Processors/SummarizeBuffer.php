<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Processors;

use Cognesy\Addons\Chat\Contracts\CanSummarizeMessages;
use Cognesy\Addons\Chat\Contracts\ScriptProcessor;
use Cognesy\Messages\Messages;
use Cognesy\Messages\Script\Script;
use Cognesy\Utils\Tokenizer;

class SummarizeBuffer implements ScriptProcessor
{
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
                // clear source section immutably
                $newScript = $newScript->replaceSection(
                    $sectionName,
                    $newScript->withSection($sectionName)->section($sectionName)->clear()
                );
            } elseif ($sectionName === $this->targetSection) {
                // set summary immutably
                $newScript = $newScript->withSectionMessages($sectionName, Messages::fromString($summary));
            } else {
                // copy other sections immutably
                $newScript = $newScript->replaceSection(
                    $sectionName,
                    $newScript->withSection($sectionName)->section($sectionName)->copyFrom($script->section($sectionName))
                );
            }
        }

        return $newScript;
    }
}
