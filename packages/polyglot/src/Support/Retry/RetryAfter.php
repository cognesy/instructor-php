<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Support\Retry;

use DateTimeImmutable;
use DateTimeZone;

final class RetryAfter
{
    public static function delayMs(?string $value, int $maxDelayMs, ?int $now = null): int
    {
        if ($value === null || $maxDelayMs <= 0) {
            return 0;
        }
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $seconds = ctype_digit($value)
            ? self::boundedInteger($value)
            : self::httpDateDelaySeconds($value, $now ?? time());
        if ($seconds === null || $seconds <= 0) {
            return 0;
        }
        if ($seconds > intdiv($maxDelayMs, 1000)) {
            return $maxDelayMs;
        }

        return min($maxDelayMs, $seconds * 1000);
    }

    private static function boundedInteger(string $value): int
    {
        $normalized = ltrim($value, '0');
        if ($normalized === '') {
            return 0;
        }
        $maximum = (string) PHP_INT_MAX;
        if (strlen($normalized) > strlen($maximum)
            || (strlen($normalized) === strlen($maximum) && strcmp($normalized, $maximum) > 0)
        ) {
            return PHP_INT_MAX;
        }

        return (int) $normalized;
    }

    private static function httpDateDelaySeconds(string $value, int $now): ?int
    {
        $timezone = new DateTimeZone('GMT');
        $date = DateTimeImmutable::createFromFormat('D, d M Y H:i:s \\G\\M\\T', $value, $timezone);
        if ($date === false || $date->format('D, d M Y H:i:s \\G\\M\\T') !== $value) {
            return null;
        }
        $errors = DateTimeImmutable::getLastErrors();
        if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            return null;
        }

        return max(0, $date->getTimestamp() - $now);
    }
}
