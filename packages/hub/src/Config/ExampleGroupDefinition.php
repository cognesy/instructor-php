<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Config;

use Cognesy\InstructorHub\Data\ExampleGroupAssignment;
use Cognesy\InstructorHub\Data\ExampleLocation;

final readonly class ExampleGroupDefinition
{
    public function __construct(
        public string $id,
        public string $title,
        public int $order,
        public ExampleSubgroupDefinitions $subgroups,
    ) {}

    public static function fromConfig(
        string $id,
        string $title,
        int $order,
        ExampleSubgroupDefinitions $subgroups,
    ): self {
        return new self($id, $title, $order, $subgroups);
    }

    public function assignmentFor(ExampleLocation $location): ?ExampleGroupAssignment
    {
        foreach ($this->subgroups as $subgroup) {
            if (!$subgroup->matches($location)) {
                continue;
            }
            return $subgroup->assignment($this->id, $this->title, $this->order);
        }

        return null;
    }
}
