<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore\Collections;

use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\Section;
use InvalidArgumentException;
use Countable;
use IteratorAggregate;
use ArrayIterator;
use Traversable;

/** @implements IteratorAggregate<int, Section> */
final readonly class Sections implements Countable, IteratorAggregate
{
    private array $sections;

    /**
     * Section names are unique - every lookup here (has(), get(), set(), merge(),
     * MessageStore::section()) matches on name and stops at the first hit, so a second
     * section sharing a name is unreachable: get() never returns it, set() never replaces
     * it, yet toMessages() still emits its messages. add() has always enforced this; the
     * constructor did not, which let both deserialization and direct construction build a
     * collection whose invariants silently do not hold.
     */
    public function __construct(Section ...$sections) {
        $seen = [];
        foreach ($sections as $section) {
            if (isset($seen[$section->name])) {
                throw new InvalidArgumentException("Section with name '{$section->name}' already exists.");
            }
            $seen[$section->name] = true;
        }
        // array_values(): filter() spreads an array_filter() result, whose keys have gaps.
        $this->sections = array_values($sections);
    }

    // CONSTRUCTORS /////////////////////////////////////////////

    /**
     * Deserialization must not fail on data a previous version was able to write, so
     * duplicate names are MERGED here rather than rejected - same policy as merge():
     * the later section's messages are appended to the first one under that name.
     */
    public static function fromArray(array $data): self {
        $sections = [];
        foreach ($data as $sectionData) {
            $section = Section::fromArray($sectionData);
            $sections[$section->name] = isset($sections[$section->name])
                ? $sections[$section->name]->appendMessages($section->messages())
                : $section;
        }
        return new self(...array_values($sections));
    }

    #[\Override]
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
        // Keyed by name so a repeated name in $names selects the section once rather than
        // building a collection with a duplicate the constructor would reject.
        $selected = [];
        foreach ($names as $name) {
            $section = $this->get($name);
            if ($section !== null) {
                $selected[$name] = $section;
            }
        }
        return new Sections(...array_values($selected));
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

    #[\Override]
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
                    $allMessages[] = $message;
                }
            }
        }
        return new Messages(...$allMessages);
    }
}
