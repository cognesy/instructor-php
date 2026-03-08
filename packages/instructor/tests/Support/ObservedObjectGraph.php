<?php declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Support;

use ReflectionObject;
use Traversable;

final class ObservedObjectGraph
{
    /**
     * @param list<string> $classes
     * @return array<string, int>
     */
    public static function summarize(mixed $value, array $classes = []): array
    {
        $counts = [];
        $seen = [];

        self::walk($value, $counts, $seen, $classes);
        ksort($counts);

        return $counts;
    }

    /**
     * @param array<string, int> $counts
     * @param array<int, true> $seen
     * @param list<string> $classes
     */
    private static function walk(mixed $value, array &$counts, array &$seen, array $classes): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                self::walk($item, $counts, $seen, $classes);
            }
            return;
        }

        if (!is_object($value)) {
            return;
        }

        $id = spl_object_id($value);
        if (isset($seen[$id])) {
            return;
        }
        $seen[$id] = true;

        $class = $value::class;
        if ($classes === [] || in_array($class, $classes, true)) {
            $counts[$class] = ($counts[$class] ?? 0) + 1;
        }

        if ($value instanceof Traversable) {
            foreach ($value as $item) {
                self::walk($item, $counts, $seen, $classes);
            }
        }

        $reflection = new ReflectionObject($value);
        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }
            if (!$property->isInitialized($value)) {
                continue;
            }
            self::walk($property->getValue($value), $counts, $seen, $classes);
        }
    }
}
