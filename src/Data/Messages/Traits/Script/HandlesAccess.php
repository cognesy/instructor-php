<?php

namespace Cognesy\Instructor\Data\Messages\Traits\Script;

use Cognesy\Instructor\Data\Messages\Script;
use Cognesy\Instructor\Data\Messages\Section;

trait HandlesAccess
{
    public function section(string $name) : Section {
        $index = $this->sectionIndex($name);
        if ($index === -1) {
            $this->createSection(new Section($name));
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
            $script->appendSection($this->section($sectionName));
        }
        return $script;
    }

    public function hasSection(string $name) : bool {
        return $this->sectionIndex($name) !== -1;
    }

    public function reduce(callable $callback, mixed $initial = null) : mixed {
        return array_reduce($this->sections, $callback, $initial);
    }

    public function map(callable $callback) : array {
        return array_map($callback, $this->sections);
    }

    public function isEmpty() : bool {
        return match (true) {
            empty($this->sections) => true,
            default => $this->reduce(fn(mixed $carry, Section $section) => $carry && $section->isEmpty(), true),
        };
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
