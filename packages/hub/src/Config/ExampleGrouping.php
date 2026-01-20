<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Config;

use Cognesy\InstructorHub\Data\ExampleGroupAssignment;
use Cognesy\InstructorHub\Data\ExampleLocation;
use Cognesy\InstructorHub\Data\ExampleLocationOrder;

final class ExampleGrouping
{
    /** @var ExampleGroupDefinition[] */
    private array $groups;

    /**
     * @param ExampleGroupDefinition[] $groups
     */
    private function __construct(array $groups)
    {
        $this->groups = $groups;
    }

    /**
     * @param ExampleGroupDefinition[] $groups
     */
    public static function fromArray(array $groups): self
    {
        return new self($groups);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function isEmpty(): bool
    {
        return $this->groups === [];
    }

    public function assignmentFor(ExampleLocation $location): ?ExampleGroupAssignment
    {
        foreach ($this->groups as $group) {
            $assignment = $group->assignmentFor($location);
            if ($assignment !== null) {
                return $assignment;
            }
        }

        return null;
    }

    /**
     * @param ExampleLocation[] $locations
     * @return ExampleLocation[]
     */
    public function sortLocations(array $locations): array
    {
        if ($this->isEmpty()) {
            return $locations;
        }

        $orders = [];
        foreach ($locations as $index => $location) {
            $assignment = $this->assignmentFor($location);
            $orders[] = ExampleLocationOrder::fromLocation($location, (int) $index, $assignment);
        }

        usort($orders, fn(ExampleLocationOrder $left, ExampleLocationOrder $right) => $left->compare($right));

        return array_map(fn(ExampleLocationOrder $order) => $order->location, $orders);
    }
}
