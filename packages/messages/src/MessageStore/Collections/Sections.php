<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore\Collections;

use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\Section;
use InvalidArgumentException;
use Countable;
use IteratorAggregate;
use ArrayIterator;
use Traversable;

final readonly class Sections implements Countable, IteratorAggregate
{
    private array $sections;

    public function __construct(Section ...$sections) {
        $this->sections = $sections;
    }

    // CONSTRUCTORS /////////////////////////////////////////////

    public static function fromArray(array $data): self {
        $sections = [];
        foreach ($data as $sectionData) {
            $sections[] = Section::fromArray($sectionData);
        }
        return new self(...$sections);
    }

    public function getIterator(): Traversable {
        return new ArrayIterator($this->sections);
    }

    // MUTATORS /////////////////////////////////////////////////

    public function add(Section ...$sections): Sections {
        foreach ($sections as $section) {
            if ($this->has($section->name)) {
                throw new InvalidArgumentException("Section with name '{$section->name}' already exists.");
            }
        }
        return new Sections(...array_merge($this->sections, $sections));
    }

    public function set(Section ...$sections): Sections {
        $newSections = $this->sections;
        foreach ($sections as $section) {
            $found = false;
            foreach ($newSections as $key => $existing) {
                if ($existing->name === $section->name) {
                    $newSections[$key] = $section;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $newSections[] = $section;
            }
        }
        return new Sections(...$newSections);
    }

    /**
     * @param string[] $names
     */
    public function select(array $names): Sections {
        if (empty($names)) {
            return new Sections(...$this->sections);
        }
        $selected = [];
        foreach ($names as $name) {
            $section = $this->get($name);
            if ($section !== null) {
                $selected[] = $section;
            }
        }
        return new Sections(...$selected);
    }

    /**
     * @param callable(Section): bool $callback
     */
    public function remove(callable $callback): Sections {
        return $this->filter(fn(Section $s) => !$callback($s));
    }

    // ACCESSORS ////////////////////////////////////////////////

    public function has(string $name): bool {
        foreach ($this->sections as $section) {
            if ($section->name === $name) {
                return true;
            }
        }
        return false;
    }

    public function get(string $name): ?Section {
        foreach ($this->sections as $section) {
            if ($section->name === $name) {
                return $section;
            }
        }
        return null;
    }

    public function all(): array {
        return $this->sections;
    }

    public function count(): int {
        return count($this->sections);
    }

    public function names(): array {
        return $this->map(fn(Section $section) => $section->name);
    }

    public function merge(Sections $other): Sections {
        $merged = $this;
        foreach ($other as $section) {
            $existing = $merged->get($section->name);
            if ($existing === null) {
                $merged = $merged->set($section);
            } else {
                $merged = $merged->set($existing->appendMessages($section->messages()));
            }
        }
        return $merged;
    }

    public function withoutEmpty(): Sections {
        $nonEmpty = [];
        foreach ($this->sections as $section) {
            $trimmed = $section->withoutEmptyMessages();
            if (!$trimmed->isEmpty()) {
                $nonEmpty[] = $trimmed;
            }
        }
        return new Sections(...$nonEmpty);
    }

    // CONVERSIONS and TRANSFORMATIONS //////////////////////////

    /**
     * @template T
     * @param callable(Section): T $callback
     * @return array<T>
     */
    public function map(callable $callback): array {
        return array_map($callback, $this->sections);
    }

    /**
     * @param callable(Section): bool $callback
     */
    public function filter(callable $callback): Sections {
        return new Sections(...array_filter($this->sections, $callback));
    }

    /**
     * @template T
     * @param callable(T, Section): T $callback
     * @param T $initial
     * @return T
     */
    public function reduce(callable $callback, mixed $initial = null): mixed {
        return array_reduce($this->sections, $callback, $initial);
    }

    public function toMessages(): Messages {
        $allMessages = [];
        foreach ($this->sections as $section) {
            foreach ($section as $message) {
                if (!$message->isEmpty()) {
                    $allMessages[] = $message->clone();
                }
            }
        }
        return new Messages(...$allMessages);
    }
}