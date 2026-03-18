<?php declare(strict_types=1);

namespace Cognesy\Instructor\Events\Support;

use DateTimeInterface;
use JsonSerializable;
use Traversable;
use UnitEnum;

final class EventValueNormalizer
{
    public static function normalize(mixed $value): mixed
    {
        return match (true) {
            $value === null,
            is_scalar($value) => $value,
            is_array($value) => self::normalizeArray($value),
            $value instanceof UnitEnum => self::normalizeEnum($value),
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            is_object($value) => self::normalizeObject($value),
            default => get_debug_type($value),
        };
    }

    /** @param array<array-key, mixed> $value */
    private static function normalizeArray(array $value): array
    {
        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = self::normalize($item);
        }
        return $normalized;
    }

    private static function normalizeObject(object $value): mixed
    {
        $normalized = match (true) {
            method_exists($value, 'toArray') => $value->toArray(),
            $value instanceof JsonSerializable => $value->jsonSerialize(),
            $value instanceof Traversable => iterator_to_array($value),
            default => get_object_vars($value),
        };

        return match (true) {
            is_array($normalized) && $normalized !== [] => self::normalizeArray($normalized),
            is_scalar($normalized),
            $normalized === null => $normalized,
            default => ['class' => $value::class],
        };
    }

    private static function normalizeEnum(UnitEnum $value): string|int
    {
        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            default => $value->name,
        };
    }
}
