<?php

namespace Cognesy\Instructor\Core\Messages\Traits\Script;

use Cognesy\Instructor\Core\Messages\Script;
use Cognesy\Instructor\Core\Messages\Section;

trait HandlesAccess
{
    public function section(string $name) : Section {
        $index = $this->sectionIndex($name);
        if ($index === -1) {
            $this->addSection(new Section($name));
            $index = $this->sectionIndex($name);
        }
        return $this->sections[$index];
    }

    /**
     * @param string|string[] $sections
     */
    public function select(string|array $sections = []) : Script {
        $names = match (true) {
            empty($sections) => array_map(fn($section) => $section->name, $this->sections),
            is_string($sections) => [$sections],
            is_array($sections) => $sections,
        };
        $script = new Script();
        $script->context = $this->context;
        foreach ($names as $sectionName) {
            $script->append($this->section($sectionName));
        }
        return $script;
    }

    public function hasSection(string $name) : bool {
        return $this->sectionIndex($name) !== -1;
    }

    // INTERNAL ////////////////////////////////////////////////////

    private function sectionIndex(string $name) : int {
        $index = -1;
        foreach ($this->sections as $i => $section) {
            if ($section->name === $name) {
                $index = $i;
                break;
            }
        }
        return $index;
    }
}
