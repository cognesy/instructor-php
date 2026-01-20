<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Data;

final readonly class ExampleLocationOrder
{
    public function __construct(
        public ExampleLocation $location,
        public string $path,
        public int $index,
        public int $groupOrder,
        public int $subgroupOrder,
    ) {}

    public static function fromLocation(
        ExampleLocation $location,
        int $index,
        ?ExampleGroupAssignment $assignment,
    ): self {
        return new self(
            location: $location,
            path: $location->path,
            index: $index,
            groupOrder: $assignment?->groupOrder ?? PHP_INT_MAX,
            subgroupOrder: $assignment?->subgroupOrder ?? PHP_INT_MAX,
        );
    }

    public function compare(self $other): int
    {
        if ($this->groupOrder !== $other->groupOrder) {
            return $this->groupOrder <=> $other->groupOrder;
        }

        if ($this->subgroupOrder !== $other->subgroupOrder) {
            return $this->subgroupOrder <=> $other->subgroupOrder;
        }

        if ($this->path !== $other->path) {
            return strcmp($this->path, $other->path);
        }

        return $this->index <=> $other->index;
    }
}
