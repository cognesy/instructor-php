<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

final readonly class EvalMatcher
{
    public static function matches(mixed $expected, mixed $actual): bool {
        return match (true) {
            $expected instanceof EvalMatch => $expected->matches($actual),
            is_array($expected) => self::partial($expected, $actual),
            default => $expected === $actual,
        };
    }

    public static function partial(mixed $expected, mixed $actual): bool {
        if (!is_array($expected)) {
            return self::matches($expected, $actual);
        }
        if (!is_array($actual)) {
            return false;
        }
        if (array_is_list($expected)) {
            return count($expected) === count($actual) && self::allEntriesMatch($expected, $actual);
        }
        foreach ($expected as $key => $value) {
            if (!array_key_exists($key, $actual) || !self::partial($value, $actual[$key])) {
                return false;
            }
        }
        return true;
    }

    /** @param array<mixed> $expected @param array<mixed> $actual */
    private static function allEntriesMatch(array $expected, array $actual): bool {
        foreach ($expected as $index => $value) {
            if (!self::partial($value, $actual[$index] ?? null)) {
                return false;
            }
        }
        return true;
    }
}
