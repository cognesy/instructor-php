<?php

declare(strict_types=1);

namespace Cognesy\Tell\Render;

use InvalidArgumentException;

final readonly class FieldSelection
{
    /**
     * @param  list<string>  $fields
     */
    private function __construct(private array $fields) {}

    /**
     * @param  list<string>  $defaults
     * @param  list<string>  $available
     */
    public static function from(string $requested, array $defaults, array $available): self {
        $fields = match ($requested) {
            '' => $defaults,
            default => array_values(array_unique(array_filter(
                array_map('trim', explode(',', $requested)),
                static fn (mixed $value): bool => (bool) $value,
            ))),
        };
        $unknown = array_values(array_diff($fields, $available));
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf(
                'Unknown field(s): %s. Available fields: %s.',
                implode(', ', $unknown),
                implode(', ', $available),
            ));
        }

        return new self($fields);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function project(array $rows): array {
        return array_map($this->projectRow(...), $rows);
    }

    /** @param array<string, mixed> $row */
    private function projectRow(array $row): array {
        return array_intersect_key($row, array_flip($this->fields));
    }
}
