<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore\Traits\MessageStore;

use Cognesy\Messages\MessageStore\Collections\Sections;
use Cognesy\Messages\MessageStore\Section;

trait HandlesAccess
{
    public function sections() : Sections {
        return $this->sections;
    }

    public function getSection(string $name) : Section {
        if (!$this->sections->has($name)) {
            return Section::empty($name);
        }
        return $this->sections->get($name);
    }

    public function sectionNames() : array {
        return $this->sections->map(fn(Section $section) => $section->name);
    }

    public function hasSection(string $name) : bool {
        return $this->sections->has($name);
    }

    public function reduce(callable $callback, mixed $initial = null) : mixed {
        return $this->sections->reduce($callback, $initial);
    }

    public function map(callable $callback) : array {
        return $this->sections->map($callback);
    }

    public function isEmpty() : bool {
        return match (true) {
            $this->sections->count() === 0 => true,
            default => $this->reduce(fn(mixed $carry, Section $section) => $carry && $section->isEmpty(), true),
        };
    }

    public function notEmpty() : bool {
        return !$this->isEmpty();
    }

    public function hasComposites() : bool {
        return $this->reduce(fn(bool $carry, Section $section) => $carry || $section->hasComposites(), false);
    }

}
