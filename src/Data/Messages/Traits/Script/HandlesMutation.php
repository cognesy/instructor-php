<?php

namespace Cognesy\Instructor\Data\Messages\Traits\Script;

use Cognesy\Instructor\Data\Messages\Script;
use Cognesy\Instructor\Data\Messages\Section;
use Exception;

trait HandlesMutation
{
    public function createSection(Section $section) : static {
        if ($this->hasSection($section->name())) {
            throw new Exception("Section with name '{$section->name()}' already exists - use mergeSection() instead.");
        }
        $this->appendSection($section);
        return $this;
    }

    public function appendSection(Section $section) : static {
        if ($this->hasSection($section->name())) {
            throw new Exception("Section with name '{$section->name()}' already exists - use mergeSection() instead.");
        }
        $this->sections = $this->appendSections([$section]);
        return $this;
    }

    public function mergeSection(Section $section) : static {
        if ($this->hasSection($section->name())) {
            $this->section($section->name())->mergeSection($section);
        } else {
            $this->appendSection($section);
        }
        return $this;
    }

    public function overrideScript(Script $script) : static {
        foreach($script->sections as $section) {
            if ($this->hasSection($section->name())) {
                $this->removeSection($section->name());
            }
            $this->appendSection($section);
        }
        $this->mergeContext($script->context);
        return $this;
    }

    public function mergeScript(Script $script) : static {
        foreach($script->sections as $section) {
            $this->mergeSection($section);
        }
        $this->mergeContext($script->context);
        return $this;
    }

    public function mergeContext(array $context) : static {
        foreach($context as $key => $value) {
            $this->context[$key] = $value;
        }
        return $this;
    }

    public function removeSection(string $name) : static {
        $this->sections = array_filter($this->sections, fn($section) => $section->name() !== $name);
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