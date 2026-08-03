<?php

declare(strict_types=1);

namespace Cognesy\Tell\Operational;

final readonly class PlaneMap
{
    /** @var list<PlaneOperation> */
    private array $operations;

    public function __construct(PlaneOperation ...$operations)
    {
        $this->operations = $operations;
    }

    public static function fromCommands(CanDescribeOperationalPlane ...$commands): self
    {
        return new self(...array_map(
            static fn (CanDescribeOperationalPlane $command): PlaneOperation => $command->planeOperation(),
            $commands,
        ));
    }

    /** @return list<array<string, string>> */
    public function toArray(): array
    {
        return array_map(
            static fn (PlaneOperation $operation): array => $operation->toArray(),
            $this->operations,
        );
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        $counts = array_fill_keys(array_column(OperationalPlane::cases(), 'value'), 0);
        foreach ($this->operations as $operation) {
            $counts[$operation->plane->value]++;
        }

        return $counts;
    }

    public function count(): int
    {
        return count($this->operations);
    }
}
