<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Internal;

use Cognesy\Polyglot\Decision\Data\JsonContent;
use InvalidArgumentException;
use stdClass;

/** @internal */
final class DecisionData
{
    private const float DISTRIBUTION_TOLERANCE = 0.02;

    /** @param list<string> $allowed */
    public static function assertKnownFields(array $data, array $allowed, string $context): void
    {
        $unknown = array_values(array_diff(array_keys($data), $allowed));
        if ($unknown !== []) {
            throw new InvalidArgumentException("Unknown {$context} fields: ".implode(', ', $unknown));
        }
    }

    public static function nonEmptyString(mixed $value, string $field): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("{$field} must be a non-empty string.");
        }

        return $value;
    }

    public static function optionalJsonContent(mixed $value, string $field): ?JsonContent
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value) || is_array($value) || $value instanceof stdClass) {
            return JsonContent::from($value);
        }

        throw new InvalidArgumentException("{$field} must be text, an object, a list, or null.");
    }

    public static function finiteFloat(mixed $value, string $field): float
    {
        if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
            throw new InvalidArgumentException("{$field} must be a finite number.");
        }

        return (float) $value;
    }

    public static function probability(mixed $value, string $field): float
    {
        $probability = self::finiteFloat($value, $field);
        if ($probability < 0.0 || $probability > 1.0) {
            throw new InvalidArgumentException("{$field} must be between 0 and 1.");
        }

        return $probability;
    }

    /** @param list<float> $probabilities */
    public static function assertProbabilityDistribution(array $probabilities, string $field): void
    {
        if ($probabilities === []) {
            throw new InvalidArgumentException("{$field} must not be empty.");
        }
        if (abs(array_sum($probabilities) - 1.0) > self::DISTRIBUTION_TOLERANCE) {
            throw new InvalidArgumentException("{$field} must sum to 1.");
        }
    }

    public static function optionalNonNegativeInt(mixed $value, string $field): ?int
    {
        if ($value === null) {
            return null;
        }
        if (! is_int($value) || $value < 0) {
            throw new InvalidArgumentException("{$field} must be a non-negative integer or null.");
        }

        return $value;
    }
}
