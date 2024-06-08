<?php

namespace Cognesy\Instructor\Data\Messages\Traits\Script;

use Cognesy\Instructor\Data\Messages\Section;

trait HandlesMutation
{
    public function addSection(Section $section) : static {
        $this->append($section);
        return $this;
    }

    public function append(Section $section) : static {
        $this->sections = $this->appendSections([$section]);
        return $this;
    }

    public function prepend(Section $section) : static {
        $this->sections = $this->prependSections([$section]);
        return $this;
    }

    public function addBefore(string $name, Section $section) : static {
        $this->sections = $this->insert($this->sections, $this->sectionIndex($name), [$section]);
        return $this;
    }

    public function addAfter(string $name, Section $section) : static {
        $this->sections = $this->insert($this->sections, $this->sectionIndex($name) + 1, [$section]);
        return $this;
    }

    // INTERNAL ////////////////////////////////////////////////////

    private function insert(array $array, int $index, array $new) : array {
        return array_merge(
            array_slice($array, 0, $index),
            $new,
            array_slice($array, $index)
        );
    }

    private function appendSections(array $array) : array {
        return array_merge($this->sections, $array);
    }

    private function prependSections(array $array) {
        return array_merge($array, $this->sections);
    }
}