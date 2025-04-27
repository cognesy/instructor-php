<?php
namespace Cognesy\Template\Script\Traits\Script;

use Cognesy\Template\Script\Script;
use Cognesy\Template\Script\Section;
use Cognesy\Utils\Messages\Message;

trait HandlesTransformation
{
    /**
     * @param string|string[] $sections
     */
    public function select(string|array $sections = []) : static {
        $names = match (true) {
            empty($sections) => array_map(fn($section) => $section->name, $this->sections),
            is_string($sections) => [$sections],
            is_array($sections) => $sections,
        };
        $script = new Script();
        $script->withParams($this->parameters);
        foreach ($names as $sectionName) {
            $script->appendSection($this->section($sectionName));
        }
        return $script;
    }

    public function toMergedPerRole() : static {
        $script = new Script();
        $script->withParams($this->parameters());
        foreach ($this->sections as $item) {
            if ($item->isEmpty()) {
                continue;
            }
            $script->appendSection($item->toMergedPerRole());
        }
        return $script;
    }

    public function toSingleSection(string $section) : static {
        $script = new Script();
        $script->withParams($this->parameters());
        foreach ($this->sections as $sourceSection) {
            $script->section($section)->appendMessages($sourceSection->messages());
        }
        return $script;
    }

    public function toSingleMessage(string $separator = "\n") : static {
        $script = new Script();
        $script->withParams($this->parameters());
        $script->appendSection(new Section('messages'));
        $script->section('messages')->appendMessage(
            new Message('user', $this->toString($separator))
        );
        return $script;
    }
}