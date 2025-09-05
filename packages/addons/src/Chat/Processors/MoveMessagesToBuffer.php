<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Processors;

use Cognesy\Addons\Chat\Contracts\ScriptProcessor;
use Cognesy\Addons\Chat\Utils\SplitMessages;
use Cognesy\Messages\Script\Script;
use Cognesy\Utils\Tokenizer;

class MoveMessagesToBuffer implements ScriptProcessor
{
    private string $sourceSection;
    private string $targetSection;
    private int $maxTokens;

    public function __construct(
        string $sourceSection,
        string $targetSection,
        int $maxTokens
    ) {
        $this->sourceSection = $sourceSection;
        $this->targetSection = $targetSection;
        $this->maxTokens = $maxTokens;
    }

    public function shouldProcess(Script $script): bool {
        $tokens = Tokenizer::tokenCount(
            $script->section($this->sourceSection)->toMessages()->toString()
        );
        return $tokens > $this->maxTokens;
    }

    public function process(Script $script): Script {
        if (!$this->shouldProcess($script)) {
            return $script;
        }

        $messages = $script->section($this->sourceSection)->toMessages();
        [$keep, $overflow] = (new SplitMessages)->split($messages, $this->maxTokens);

        $newScript = new Script();
        foreach ($script->sections() as $section) {
            $sectionName = $section->name();
            if ($sectionName === $this->sourceSection) {
                $newScript = $newScript->withSectionMessages($sectionName, $keep);
            } elseif ($sectionName === $this->targetSection) {
                $existingMessages = $script->section($sectionName)->toMessages();
                $combined = $existingMessages->appendMessages($overflow);
                $newScript = $newScript->withSectionMessages($sectionName, $combined);
            } else {
                $newScript = $newScript->replaceSection(
                    $sectionName,
                    $newScript->withSection($sectionName)->section($sectionName)->copyFrom($script->section($sectionName))
                );
            }
        }

        return $newScript;
    }
}
