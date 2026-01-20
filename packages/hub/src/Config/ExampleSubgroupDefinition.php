<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Config;

use Cognesy\InstructorHub\Data\ExampleGroupAssignment;
use Cognesy\InstructorHub\Data\ExampleLocation;

final readonly class ExampleSubgroupDefinition
{
    public function __construct(
        public string $id,
        public string $title,
        public int $order,
        public ExampleMatchRules $includes,
        public ExampleMatchRules $excludes,
    ) {}

    public static function fromConfig(
        string $id,
        string $title,
        int $order,
        ExampleMatchRules $includes,
        ExampleMatchRules $excludes,
    ): self {
        return new self($id, $title, $order, $includes, $excludes);
    }

    public function matches(ExampleLocation $location): bool
    {
        if (!$this->includes->matches($location)) {
            return false;
        }

        if ($this->excludes->matches($location)) {
            return false;
        }

        return true;
    }

    public function assignment(string $groupId, string $groupTitle, int $groupOrder): ExampleGroupAssignment
    {
        return new ExampleGroupAssignment(
            tab: $groupId,
            group: $this->id,
            groupTitle: $this->formatGroupTitle($groupTitle),
            groupOrder: $groupOrder,
            subgroupOrder: $this->order,
        );
    }

    private function formatGroupTitle(string $groupTitle): string
    {
        if ($groupTitle === '') {
            return $this->title;
        }

        return $groupTitle . ' \\ ' . $this->title;
    }
}
