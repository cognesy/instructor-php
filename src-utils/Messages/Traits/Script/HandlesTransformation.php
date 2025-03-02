<?php
namespace Cognesy\Utils\Messages\Traits\Script;

use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Script;
use Cognesy\Utils\Messages\Section;

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