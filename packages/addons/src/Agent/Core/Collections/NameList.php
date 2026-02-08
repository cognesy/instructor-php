<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/instructor-agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Core\Collections;

final readonly class NameList
{
    /** @var array<int, string> */
    private array $names;

    public function __construct(
        string ...$names,
    ) {
        $unique = array_values(array_unique($names));
        $this->names = $unique;
    }

    public static function fromArray(array $names): self {
        $clean = [];
        foreach ($names as $name) {
            if (is_string($name) && $name !== '') {
                $clean[] = $name;
            }
        }
        return new self(...$clean);
    }

    /** @return array<int, string> */
    public function all(): array {
        return $this->names;
    }

    public function count(): int {
        return count($this->names);
    }

    public function isEmpty(): bool {
        return $this->count() === 0;
    }

    public function has(string $name): bool {
        return in_array($name, $this->names, true);
    }

    public function merge(self $other): self {
        return new self(...array_merge($this->names, $other->all()));
    }

    public function toArray(): array {
        return $this->names;
    }
}
