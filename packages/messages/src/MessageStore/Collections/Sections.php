<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore\Collections;

use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\Section;
use InvalidArgumentException;

final readonly class Sections
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

    // MUTATORS /////////////////////////////////////////////////

    public function add(Section ...$sections): Sections {
        foreach ($sections as $section) {
            if ($this->has($section->name)) {
                throw new InvalidArgumentException("Section with name '{$section->name}' already exists.");
            }
        }
        return new Sections(...array_merge($this->sections, $sections));
    }

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

    public function each(): iterable {
        foreach ($this->sections as $section) {
            yield $section;
        }
    }

    public function count(): int {
        return count($this->sections);
    }

    public function names(): array {
        return $this->map(fn(Section $section) => $section->name);
    }

    // CONVERSIONS and TRANSFORMATIONS //////////////////////////

    public function map(callable $callback): array {
        return array_map($callback, $this->sections);
    }

    public function filter(callable $callback): Sections {
        return new Sections(...array_filter($this->sections, $callback));
    }

    public function reduce(callable $callback, mixed $initial = null): mixed {
        return array_reduce($this->sections, $callback, $initial);
    }

    public function toMessages(): Messages {
        $messages = Messages::empty();
        foreach ($this->sections as $section) {
            foreach($section->messages()->each() as $message) {
                if ($message->isEmpty()) {
                    continue;
                }
                $messages = $messages->appendMessage($message->clone());
            }
        }
        return $messages;
    }
}