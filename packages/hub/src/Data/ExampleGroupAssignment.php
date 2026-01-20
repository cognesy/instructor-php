<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Data;

final readonly class ExampleGroupAssignment
{
    public function __construct(
        public string $tab,
        public string $group,
        public string $groupTitle,
        public int $groupOrder,
        public int $subgroupOrder,
    ) {}
}
