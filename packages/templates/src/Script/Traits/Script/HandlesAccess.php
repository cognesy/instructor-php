<?php declare(strict_types=1);

namespace Cognesy\Template\Script\Traits\Script;

use Cognesy\Template\Script\Section;

trait HandlesAccess
{
    /** @return Section[] */
    public function sections() : array {
        return $this->sections;
    }

    public function section(string $name) : Section {
        $index = $this->sectionIndex($name);
        if ($index === -1) {
            $section = $this->createSection($name);
        } else {
            $section = $this->sections[$index];
        }
        return $section;
    }

    public function sectionNames() : array {
        return $this->map(fn(Section $section) => $section->name);
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

    public function notEmpty() : bool {
        return !$this->isEmpty();
    }

    public function hasComposites() : bool {
        return $this->reduce(fn(bool $carry, Section $section) => $carry || $section->hasComposites(), false);
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
